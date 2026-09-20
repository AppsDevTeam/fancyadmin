<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Vypíše SQL, kterým se založí cílové úložiště logů (viz fancyadmin:move-logs).
 *
 * Schéma se odvozuje z entit, takže neodejde od zdroje - přibude sloupec v logu a příští
 * výpis ho má taky. Ručně psané SQL vedle entit se rozejde a přijde se na to až tím,
 * že odvoz spadne na neznámém sloupci.
 *
 * NENÍ TO MIGRACE. Výpis je podklad, který si někdo přečte a pustí ručně na serveru,
 * kam aplikace nemá přístup - a to je celý smysl odděleného úložiště. Vytvoření uživatele
 * a databáze je v něm proto taky, i když ho aplikace sama provést nemůže.
 */
#[AsCommand(name: 'fancyadmin:print-log-schema', description: 'Vypíše SQL pro založení cílového úložiště logů')]
class PrintLogSchemaCommand extends Command
{
	/**
	 * @param list<array{entity: class-string, table: string|null, hot: string|null, retention: string|null}> $config
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly Connection $targetConnection,
		private readonly array $config,
	) {
		parent::__construct();
	}

	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		$platform = $this->targetConnection->getDatabasePlatform();
		$isPostgres = $platform instanceof PostgreSQLPlatform;

		$output->writeln($this->header());
		$output->writeln($this->createDatabase($isPostgres));

		foreach ($this->config as $_entry) {
			$meta = $this->em->getClassMetadata($_entry['entity']);
			$targetTable = $_entry['table'] ?? $meta->getTableName();
			$timescale = $isPostgres && ($_entry['hot'] !== null || $_entry['retention'] !== null);

			$schema = new Schema();
			$this->buildTable($meta, $schema->createTable($targetTable), $timescale);

			$output->writeln('');
			$output->writeln('-- ' . $targetTable);
			foreach ($schema->toSql($platform) as $_sql) {
				$output->writeln($_sql . ';');
			}

			if ($timescale) {
				$output->writeln($this->timescale($targetTable, $_entry['hot'], $_entry['retention']));
			}
		}

		return self::SUCCESS;
	}

	/**
	 * Tabulka se staví z metadat entity, ne z ručně psaného SQL - to by se se schématem
	 * rozešlo a přišlo by se na to až tím, že odvoz spadne na neznámém sloupci.
	 *
	 * CIZÍ KLÍČE se nepřenášejí: v cíli leží tabulky nezávisle na sobě a odvoz je po
	 * dávkách, takže by tělo požadavku mohlo dorazit dřív než jeho hlavička a cizí klíč
	 * by ho odmítl. Sloupec s odkazem zůstává, jen bez vynucení.
	 *
	 * ID SE NEGENERUJE. Ve zdroji je to auto_increment, tady ne - hodnotu přiváží mover
	 * a podle ní pozná, co už odvezl. Kdyby si ji cíl přiděloval sám, ztratí se vazba
	 * na zdroj a opakovaný běh po přerušení by zapsal totéž podruhé.
	 */
	private function buildTable(ClassMetadata $meta, Table $table, bool $timescale): void
	{
		foreach ($meta->getFieldNames() as $_field) {
			$mapping = $meta->getFieldMapping($_field);

			$table->addColumn($meta->getColumnName($_field), $mapping['type'], array_filter([
				'notnull' => !($mapping['nullable'] ?? false),
				'length' => $mapping['length'] ?? null,
				'precision' => $mapping['precision'] ?? null,
				'scale' => $mapping['scale'] ?? null,
			], static fn ($value) => $value !== null));
		}

		// vlastnická toOne vazba je ve zdroji sloupec s id, tady z ní zbude jen to id
		foreach ($meta->getAssociationMappings() as $_association) {
			if (!($_association['isOwningSide'] ?? false) || !isset($_association['joinColumns'])) {
				continue;
			}

			foreach ($_association['joinColumns'] as $_joinColumn) {
				$table->addColumn($_joinColumn['name'], 'bigint', ['notnull' => !($_joinColumn['nullable'] ?? true)]);
			}
		}

		// Hypertable vyžaduje dělicí sloupec v každém unikátním klíči, takže k id přibývá
		// created_at. Bez dělení stačí id samotné.
		$primary = $meta->getIdentifierColumnNames();
		$table->setPrimaryKey($timescale && $table->hasColumn('created_at') ? [...$primary, 'created_at'] : $primary);

		foreach ($meta->table['indexes'] ?? [] as $_name => $_index) {
			$columns = $_index['columns'] ?? array_map($meta->getColumnName(...), $_index['fields'] ?? []);
			if ($columns) {
				$table->addIndex($columns, is_string($_name) ? $_name : null);
			}
		}
	}

	private function timescale(string $table, ?string $hot, ?string $retention): string
	{
		$sql = ["SELECT create_hypertable('$table', 'created_at');"];

		if ($hot !== null) {
			// hranice mezi provozní a archivní vrstvou: komprimovaná data jdou číst dál,
			// ale s prodlevou na dekompresi
			$sql[] = "ALTER TABLE $table SET (timescaledb.compress, timescaledb.compress_orderby = 'created_at DESC');";
			$sql[] = "SELECT add_compression_policy('$table', INTERVAL '$hot');";
		}

		if ($retention !== null) {
			$sql[] = "SELECT add_retention_policy('$table', INTERVAL '$retention');";
		}

		return implode("\n", $sql);
	}

	private function createDatabase(bool $isPostgres): string
	{
		$params = $this->targetConnection->getParams();
		$dbname = $params['dbname'] ?? 'logdb';
		$user = $params['user'] ?? 'logdb';

		if (!$isPostgres) {
			return implode("\n", [
				"CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
				"CREATE USER '$user'@'%' IDENTIFIED BY '<heslo>';",
				"GRANT SELECT, INSERT ON `$dbname`.* TO '$user'@'%';",
			]);
		}

		return implode("\n", [
			'CREATE EXTENSION IF NOT EXISTS timescaledb;',
			'',
			"CREATE USER $user WITH PASSWORD '<heslo>';",
			"CREATE DATABASE \"$dbname\" WITH OWNER = $user ENCODING = 'UTF8' TEMPLATE = template0;",
			'',
			'-- Dál se pokračuje připojený k této databázi.',
		]);
	}

	private function header(): string
	{
		return implode("\n", [
			'-- Cílové úložiště logů, vygenerováno z entit příkazem fancyadmin:print-log-schema.',
			'--',
			'-- Databáze patří VŽDY JEN JEDNOMU zdroji: záznam si veze své id ze zdrojové',
			'-- databáze, takže dva zdroje v jedné tabulce by si je přepsaly.',
			'--',
			'-- Heslo doplňte, ve výpisu schválně není. Aplikaci stačí právo číst a zapisovat;',
			'-- mazat má jen retenční politika, aby se odvezený záznam nedal odstranit odtud,',
			'-- odkud přišel.',
			'',
		]);
	}
}
