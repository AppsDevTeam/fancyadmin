<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
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
			$this->buildTable($meta, $schema->createTable($targetTable), $timescale, $isPostgres);

			$output->writeln('');
			$output->writeln('-- ' . $targetTable);
			foreach ($schema->toSql($platform) as $_sql) {
				$output->writeln($_sql . ';');
			}

			if ($timescale) {
				$output->writeln($this->timescale($targetTable, $_entry['hot'], $_entry['retention']));
			}
		}

		$params = $this->targetConnection->getParams();
		$output->writeln($this->grants(
			$isPostgres,
			($params['dbname'] ?? '') ?: '<databaze>',
			($params['user'] ?? '') ?: '<uzivatel>',
		));

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
	private function buildTable(ClassMetadata $meta, Table $table, bool $timescale, bool $withTimeZone): void
	{
		foreach ($meta->getFieldNames() as $_field) {
			$mapping = $meta->getFieldMapping($_field);

			$table->addColumn($meta->getColumnName($_field), $this->resolveType($mapping['type'], $withTimeZone), array_filter([
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

	/**
	 * Cas se v cili uklada SE ZONOU, i kdyz ho zdroj ma bez ni.
	 *
	 * Zdrojove logy jsou v UTC, ale sloupec to nerika - a mover proto posila hodnotu
	 * s vyslovnym offsetem. Kdyby ji cil prijal do sloupce bez zony, offset zahodi a po
	 * case uz z dat nepozna, v cem jsou; se zonou je to jednoznacne i za rok.
	 * MySQL zonu u DATETIME neumi, takze tam zustava puvodni typ.
	 */
	private function resolveType(string $type, bool $withTimeZone): string
	{
		if (!$withTimeZone) {
			return $type;
		}

		return match ($type) {
			Types::DATETIME_MUTABLE => Types::DATETIMETZ_MUTABLE,
			Types::DATETIME_IMMUTABLE => Types::DATETIMETZ_IMMUTABLE,
			default => $type,
		};
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

	/**
	 * DVA UŽIVATELÉ, ne jeden.
	 *
	 * Vlastník založí tabulky a patří mu retenční politiky. Aplikace dostane účet, který
	 * umí jen ČÍST A ZAPISOVAT - žádné UPDATE, DELETE, DROP ani ALTER. Bez toho celé
	 * oddělené úložiště nedává smysl: kdo se dostane k aplikaci, mohl by přepsat záznamy
	 * o tom, co v ní dělal, a to je přesně ta vlastnost, kterou má úložiště zaručit.
	 *
	 * Aplikace potřebuje i SELECT: odvoz podle id poznává, co už v cíli je, a sekce Logy
	 * v administraci odtud čtou. Mazání zůstává výhradně retenční politice.
	 */
	private function createDatabase(bool $isPostgres): string
	{
		// prazdny retezec je v konfiguraci bezny (hodnotu doplnuje az stage), takze
		// ?: misto ?? - jinak by ve vypisu zustalo CREATE DATABASE "" a nikdo si toho
		// nemusi vsimnout
		$params = $this->targetConnection->getParams();
		$dbname = ($params['dbname'] ?? '') ?: '<databaze>';
		$user = ($params['user'] ?? '') ?: '<uzivatel>';

		if (!$isPostgres) {
			return implode("\n", [
				"CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
				'',
				'-- vlastnik schematu - zaklada tabulky, aplikace jeho udaje nezna',
				"CREATE USER '<vlastnik>'@'%' IDENTIFIED BY '<heslo vlastnika>';",
				"GRANT ALL PRIVILEGES ON `$dbname`.* TO '<vlastnik>'@'%';",
				'',
				'-- aplikace - jen cist a zapisovat',
				"CREATE USER '$user'@'%' IDENTIFIED BY '<heslo>';",
				"GRANT SELECT, INSERT ON `$dbname`.* TO '$user'@'%';",
				'',
				'-- Dál se pokračuje jako <vlastnik>, připojený k této databázi.',
			]);
		}

		return implode("\n", [
			'CREATE EXTENSION IF NOT EXISTS timescaledb;',
			'',
			'-- vlastnik schematu - zaklada tabulky a patri mu retencni politiky,',
			'-- aplikace jeho udaje nezna',
			"CREATE USER <vlastnik> WITH PASSWORD '<heslo vlastnika>';",
			"CREATE DATABASE \"$dbname\" WITH OWNER = <vlastnik> ENCODING = 'UTF8' TEMPLATE = template0;",
			'',
			'-- aplikace - prava dostane az za tabulkami, viz konec vypisu',
			"CREATE USER $user WITH PASSWORD '<heslo>';",
			'',
			'-- Dál se pokračuje jako <vlastnik>, připojený k této databázi.',
		]);
	}

	/**
	 * Práva aplikace. Až za tabulkami - grant na neexistující tabulku neprojde.
	 *
	 * Chunky hypertabulky vznikají za provozu; TimescaleDB jim práva rodiče předá sama,
	 * `ALTER DEFAULT PRIVILEGES` kryje tabulky založené později (další log v konfiguraci).
	 */
	private function grants(bool $isPostgres, string $dbname, string $user): string
	{
		if (!$isPostgres) {
			// MySQL resi prava uz u CREATE USER, tady uz neni co dodat
			return '';
		}

		return implode("\n", [
			'',
			'-- Práva aplikace: číst a zapisovat, nic víc. Mazat smí jen retenční politika,',
			'-- aby se odvezený záznam nedal odstranit odtud, odkud přišel.',
			"GRANT CONNECT ON DATABASE \"$dbname\" TO $user;",
			"GRANT USAGE ON SCHEMA public TO $user;",
			"GRANT SELECT, INSERT ON ALL TABLES IN SCHEMA public TO $user;",
			"ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT ON TABLES TO $user;",
			"REVOKE CREATE ON SCHEMA public FROM PUBLIC;",
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
			'-- Hesla doplňte, ve výpisu schválně nejsou. Uživatelé jsou dva: vlastník, který',
			'-- schéma založí, a aplikace, která umí jen číst a zapisovat - žádné UPDATE,',
			'-- DELETE, DROP ani ALTER. Mazat smí jen retenční politika, aby se odvezený',
			'-- záznam nedal odstranit odtud, odkud přišel.',
			'',
		]);
	}
}
