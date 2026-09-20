<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Odváží logovací záznamy do odděleného úložiště a ze zdroje je maže.
 *
 * Logovací tabulky v aplikaci jsou jen přestupní stanice. Logy se drží mnohem déle, než
 * má smysl zatěžovat provozní databázi, a auditní stopa má navíc být jinde než systém,
 * o kterém vypovídá - kdo se dostane k aplikaci, nesmí umět přepsat záznamy o tom,
 * co v ní dělal.
 *
 * Co se odváží, říká konfigurace (viz FancyAdminExtension, klíč `logMover`). Rozdíl proti
 * mazání retenčním jobem je v tom, že tudy záznam neodchází ze světa - jen se stěhuje,
 * takže sem patří i to, co se podle retenční politiky musí uchovat dlouho.
 *
 * ID SE VEZE S SEBOU. Záznam má v cíli totéž id jako měl ve zdroji, takže jde dohledat
 * zpátky a odkazy mezi odvezenými tabulkami (request_log_body -> request_log) dál sedí.
 * Předpokladem je JEDNA CÍLOVÁ DATABÁZE NA ZDROJ - id se mezi systémy potkávají, takže
 * dva zdroje v jedné tabulce by si je přepsaly.
 *
 * POŘADÍ OPERACÍ: nejdřív zápis do cíle, pak teprve mazání ve zdroji, a mazat se smí jen
 * to, co se opravdu zapsalo. Kdyby se běh přerušil mezi zápisem a mazáním, zůstanou
 * záznamy v obou - a další běh je podle id pozná a přeskočí. Opačné pořadí nebo mazání
 * "co se stihlo" znamená ztrátu, kterou nikdo nedohledá, protože záznam o ní byl právě
 * v tom, co zmizelo.
 */
#[AsCommand(name: 'fancyadmin:move-logs', description: 'Odveze logy do odděleného úložiště')]
class MoveLogsCommand extends Command
{
	private const int BATCH_SIZE = 1000;

	/**
	 * @param list<array{entity: class-string, table: string|null}> $config
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly Connection $targetConnection,
		private readonly array $config,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Jen spočítá, co by odvezl');
		$this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Počet řádků na dávku', (string) self::BATCH_SIZE);
		$this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nejvýš tolik řádků na tabulku za běh (0 = bez omezení)', '0');
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$batchSize = max(1, (int) $input->getOption('batch-size'));
		$limit = max(0, (int) $input->getOption('limit'));
		$dryRun = (bool) $input->getOption('dry-run');

		if (!$this->config) {
			$io->warning('Konfigurace `logMover` je prázdná, není co odvážet.');

			return self::SUCCESS;
		}

		$rows = [];
		$failed = false;

		foreach ($this->config as $_entry) {
			$sourceTable = $this->em->getClassMetadata($_entry['entity'])->getTableName();
			$targetTable = $_entry['table'] ?? $sourceTable;

			try {
				$count = $dryRun
					? $this->countWaiting($sourceTable)
					: $this->move($sourceTable, $targetTable, $batchSize, $limit);
			} catch (Throwable $e) {
				// jedna nedostupná nebo rozbitá tabulka nesmí zastavit odvoz ostatních
				$io->error($sourceTable . ': ' . $e->getMessage());
				$rows[] = [$sourceTable, $targetTable, 'CHYBA'];
				$failed = true;
				continue;
			}

			$rows[] = [$sourceTable, $targetTable, $count];
		}

		$io->table(['zdroj', 'cíl', $dryRun ? 'k odvozu' : 'odvezeno'], $rows);

		return $failed ? self::FAILURE : self::SUCCESS;
	}

	/** @throws Throwable */
	private function move(string $sourceTable, string $targetTable, int $batchSize, int $limit): int
	{
		$source = $this->em->getConnection();
		$moved = 0;
		$processed = 0;

		while (true) {
			// nejstarší napřed: kdyby běh skončil dřív, zůstane ve zdroji ta novější část,
			// kterou je i tak nejsnazší dohledat
			$batch = $source->fetchAllAssociative("SELECT * FROM $sourceTable ORDER BY id ASC LIMIT $batchSize");
			if (!$batch) {
				break;
			}

			$ids = array_map(static fn (array $row) => (int) $row['id'], $batch);
			$alreadyThere = $this->findAlreadyMoved($targetTable, $ids);

			$this->targetConnection->beginTransaction();
			try {
				foreach ($batch as $_row) {
					if (isset($alreadyThere[(int) $_row['id']])) {
						continue;
					}

					$this->targetConnection->insert($targetTable, $this->toTargetRow($_row));
				}
				$this->targetConnection->commit();
			} catch (Throwable $e) {
				$this->targetConnection->rollBack();

				// ze zdroje se nemaže nic: co se nezapsalo, nesmí zmizet
				throw $e;
			}

			// až teď, a jen to, co v cíli prokazatelně je
			$source->executeStatement(
				"DELETE FROM $sourceTable WHERE id IN (?)",
				[$ids],
				[ArrayParameterType::INTEGER],
			);

			$moved += count($batch) - count($alreadyThere);
			$processed += count($batch);

			if ($limit > 0 && $processed >= $limit) {
				break;
			}
		}

		return $moved;
	}

	private function countWaiting(string $sourceTable): int
	{
		return (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM $sourceTable");
	}

	/**
	 * Které z těchto id už v cíli jsou.
	 *
	 * @param list<int> $ids
	 * @return array<int, true>
	 */
	private function findAlreadyMoved(string $targetTable, array $ids): array
	{
		$existing = $this->targetConnection->fetchFirstColumn(
			"SELECT id FROM $targetTable WHERE id IN (?)",
			[$ids],
			[ArrayParameterType::INTEGER],
		);

		return array_fill_keys(array_map('intval', $existing), true);
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
