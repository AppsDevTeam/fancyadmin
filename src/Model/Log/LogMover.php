<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Log;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Odváží logovací záznamy do odděleného úložiště a ze zdroje je maže.
 *
 * Logovací tabulky v aplikaci jsou jen přestupní stanice. Logy se drží mnohem déle, než
 * má smysl zatěžovat provozní databázi, a auditní stopa má navíc být jinde než systém,
 * o kterém vypovídá - kdo se dostane k aplikaci, nesmí umět přepsat záznamy o tom,
 * co v ní dělal.
 *
 * Služba, ne jen command: odvoz má běžet často (dokument slibuje "v řádu minut"), takže
 * ho projekt typicky pouští z fronty, kam ho každou minutu pošle cron. Command i job pak
 * volají totéž.
 *
 * ID SE VEZE S SEBOU. Záznam má v cíli totéž id jako měl ve zdroji, takže jde dohledat
 * zpátky a odkazy mezi odvezenými tabulkami (request_log_body -> request_log) dál sedí.
 * Předpokladem je JEDNA CÍLOVÁ DATABÁZE NA ZDROJ - id se mezi systémy potkávají, takže
 * dva zdroje v jedné tabulce by si je přepsaly.
 *
 * NE VŠECHNO JE HNED ZRALÉ. Do tabulky, kde vzniká záznam o requestu a odpověď se dopisuje
 * později, se smí sáhnout až když je hotová - odvezený řádek už aplikace ve zdroji nenajde.
 * K tomu je v konfiguraci `where`.
 *
 * POŘADÍ OPERACÍ: nejdřív zápis do cíle, pak teprve mazání ve zdroji, a mazat se smí jen
 * to, co se opravdu zapsalo. Kdyby se běh přerušil mezi zápisem a mazáním, zůstanou
 * záznamy v obou - a další běh na nich skončí na duplicitě klíče a přeskočí je. Opačné
 * pořadí nebo mazání "co se stihlo" znamená ztrátu, kterou nikdo nedohledá, protože
 * záznam o ní byl právě v tom, co zmizelo.
 *
 * PAMĚŤ NEROSTE S ŠÍŘKOU TABULKY. Řádky se ze zdroje berou po jednom, ne celá dávka
 * jedním `SELECT *` - logovací záznam může mít megabajty (tělo requestu, celé XML
 * odpovědi) a tisíc takových najednou přeteče memory_limit konzumenta fronty.
 *
 * Z CÍLE SE NEČTE. Aplikaci proto stačí na cílové tabulce právo INSERT - u auditní stopy
 * je to podstatné, ta z aplikace číst nemá jít vůbec.
 */
class LogMover
{
	public const int BATCH_SIZE = 1000;

	/**
	 * @param list<array{entity: class-string, table: string|null, hot: string|null, retention: string|null, where: string|null}> $config
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly Connection $targetConnection,
		private readonly array $config,
	) {
	}

	/** @return list<array{entity: class-string, table: string|null, hot: string|null, retention: string|null, where: string|null}> */
	public function getTables(): array
	{
		return $this->config;
	}

	/**
	 * Odveze všechny nakonfigurované tabulky.
	 *
	 * Nedostupná nebo rozbitá tabulka nezastaví ostatní - jinak by stačil jeden překlep
	 * v konfiguraci a nevozilo by se nic. Volající se z `errors` dozví, co selhalo:
	 * command to vypíše, job na tom spadne, aby ho fronta zopakovala.
	 *
	 * @return array{moved: array<string, int>, errors: array<string, Throwable>}
	 */
	public function moveAll(int $batchSize = self::BATCH_SIZE, int $limit = 0): array
	{
		$moved = [];
		$errors = [];

		foreach ($this->config as $_entry) {
			$sourceTable = $this->getSourceTable($_entry['entity']);

			try {
				$moved[$sourceTable] = $this->move($_entry['entity'], $batchSize, $limit);
			} catch (Throwable $e) {
				$errors[$sourceTable] = $e;
			}
		}

		return ['moved' => $moved, 'errors' => $errors];
	}

	/**
	 * @param class-string $entityClass
	 * @throws Throwable
	 */
	public function move(string $entityClass, int $batchSize = self::BATCH_SIZE, int $limit = 0): int
	{
		$sourceTable = $this->getSourceTable($entityClass);
		$targetTable = $this->getTargetTable($entityClass);
		$source = $this->em->getConnection();
		$condition = $this->getCondition($entityClass);
		$moved = 0;
		$processed = 0;

		while (true) {
			// NEJDŘÍV JEN ID, teprve pak řádky po jednom. `SELECT *` na celou dávku vypadá
			// úsporněji (jeden dotaz místo tisíce), jenže logovací řádek může mít megabajty -
			// tělo requestu, celé XML odpovědi - a ovladač si celý výsledek drží v paměti.
			// Tisíc takových řádků spolehlivě přeteče memory_limit konzumenta fronty.
			// Takhle je spotřeba paměti stejná pro úzkou i širokou tabulku.
			//
			// nejstarší napřed: kdyby běh skončil dřív, zůstane ve zdroji ta novější část,
			// kterou je i tak nejsnazší dohledat
			$ids = array_map('intval', $source->fetchFirstColumn(
				"SELECT id FROM $sourceTable$condition ORDER BY id ASC LIMIT $batchSize",
			));
			if (!$ids) {
				break;
			}

			$this->targetConnection->beginTransaction();
			try {
				$written = 0;
				foreach ($ids as $_id) {
					$row = $source->fetchAssociative("SELECT * FROM $sourceTable WHERE id = ?", [$_id]);
					if ($row === false) {
						// mezitim zmizel - mazat ho uz nikdo nemusi
						continue;
					}

					$written += $this->insertIgnoringDuplicates($targetTable, $this->toTargetRow($row));
					unset($row);
				}
				$this->targetConnection->commit();
			} catch (Throwable $e) {
				$this->targetConnection->rollBack();

				// ze zdroje se nemaže nic: co se nezapsalo, nesmí zmizet
				throw $e;
			}

			// až teď, a jen to, co v cíli prokazatelně je: zápis buď prošel, nebo
			// se zastavil na duplicitě, což znamená, že tam záznam už byl
			$source->executeStatement(
				"DELETE FROM $sourceTable WHERE id IN (?)",
				[$ids],
				[ArrayParameterType::INTEGER],
			);

			$moved += $written;
			$processed += count($ids);

			if ($limit > 0 && $processed >= $limit) {
				break;
			}
		}

		return $moved;
	}

	/** @param class-string $entityClass */
	public function countWaiting(string $entityClass): int
	{
		$table = $this->getSourceTable($entityClass);
		$condition = $this->getCondition($entityClass);

		return (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM $table$condition");
	}

	/** @param class-string $entityClass */
	public function getSourceTable(string $entityClass): string
	{
		return $this->em->getClassMetadata($entityClass)->getTableName();
	}

	/** @param class-string $entityClass */
	public function getTargetTable(string $entityClass): string
	{
		foreach ($this->config as $_entry) {
			if ($_entry['entity'] === $entityClass) {
				return $_entry['table'] ?? $this->getSourceTable($entityClass);
			}
		}

		return $this->getSourceTable($entityClass);
	}

	/**
	 * Podmínka zralosti na odvoz, i s klíčovým slovem - prázdný řetězec, když žádná není.
	 *
	 * Musí být v SELECTu, ne až v mazání: maže se podle id toho, co se opravdu odvezlo,
	 * takže nezralý řádek se sem vůbec nesmí dostat.
	 *
	 * @param class-string $entityClass
	 */
	private function getCondition(string $entityClass): string
	{
		foreach ($this->config as $_entry) {
			if ($_entry['entity'] === $entityClass && !empty($_entry['where'] ?? null)) {
				return ' WHERE ' . $_entry['where'];
			}
		}

		return '';
	}

	/**
	 * Zapíše řádek a mlčky přeskočí ten, který už v cíli je.
	 *
	 * BEZ ČTENÍ CÍLE. Dřív se napřed zjišťovalo, která id už tam jsou - jenže pak
	 * aplikace musí mít na cílové tabulce právo SELECT, a u auditní stopy je to přesně
	 * to, co dokument slibuje vyloučit. Takhle jí stačí INSERT.
	 *
	 * Řeší to duplicita klíče, ne zahazování chyb: `INSERT IGNORE` na MySQL polyká
	 * i useknutou hodnotu nebo nesedící typ, takže by se ze zdroje smazalo něco, co
	 * v cíli není. Proto se na duplicitu míří výslovně.
	 *
	 * @param array<string, mixed> $row
	 * @return int 1 = zapsáno, 0 = v cíli už bylo
	 */
	private function insertIgnoringDuplicates(string $targetTable, array $row): int
	{
		$platform = $this->targetConnection->getDatabasePlatform();
		$columns = array_map($platform->quoteSingleIdentifier(...), array_keys($row));
		$placeholders = implode(', ', array_fill(0, count($row), '?'));

		$sql = 'INSERT INTO ' . $targetTable . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
		$sql .= $platform instanceof PostgreSQLPlatform
			? ' ON CONFLICT DO NOTHING'
			// ne INSERT IGNORE: `id = id` se dotkne jen duplicity, ostatni chyby probublaji
			: ' ON DUPLICATE KEY UPDATE ' . $columns[0] . ' = ' . $columns[0];

		return (int) $this->targetConnection->executeStatement($sql, array_values($row));
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function toTargetRow(array $row): array
	{
		// Čas jde ze zdroje bez zóny, ale je v UTC. Cíl může mít sloupec se zónou - pak by
		// si hodnotu bez offsetu vyložil podle své vlastní zóny a záznamy by se posunuly
		// o pár hodin. Tiše: nic nespadne, jen přestanou sedět s ostatními logy.
		if (isset($row['created_at']) && is_string($row['created_at'])) {
			$row['created_at'] = rtrim($row['created_at']) . '+00:00';
		}

		return $row;
	}
}
