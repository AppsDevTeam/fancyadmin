<?php

declare(strict_types=1);

use ADT\FancyAdmin\Console\PrintLogSchemaCommand;
use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use Tester\Assert;

/**
 * Vypis SQL pro zalozeni ciloveho uloziste logu.
 *
 * Schema se odvozuje z entit, aby neodesnlo od zdroje - rucne psane SQL vedle entit se
 * rozejde a prijde se na to az tim, ze odvoz spadne na neznamem sloupci.
 */

require __DIR__ . '/bootstrap.php';


/** Cilove spojeni; platformu si test vybira, at je videt rozdil v dialektu. */
final class SchemaConnection extends Doctrine\DBAL\Connection
{
	public function __construct(
		private array $connectionParams = ['dbname' => 'pokladna_cashdesk', 'user' => 'pokladna'],
		private ?Doctrine\DBAL\Platforms\AbstractPlatform $platform = null,
	) {
	}

	public function getDatabasePlatform(): Doctrine\DBAL\Platforms\AbstractPlatform
	{
		return $this->platform ?? new PostgreSQLPlatform();
	}

	public function getParams(): array
	{
		return $this->connectionParams;
	}
}

function printSchema(array $tables, ?SchemaConnection $target = null): string
{
	// TestEntityManager staci: command cte jen metadata, do zdrojove databaze nesahá
	$em = new TestEntityManager([TestAuditLog::class]);
	$meta = $em->getClassMetadata(TestAuditLog::class);
	$meta->setPrimaryTable(['name' => 'audit_log']);
	$meta->setIdGeneratorType(Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_IDENTITY);
	$meta->mapField(['fieldName' => 'id', 'type' => 'bigint', 'id' => true]);
	$meta->mapField(['fieldName' => 'action', 'type' => 'string', 'length' => 255]);
	$meta->mapField(['fieldName' => 'createdAt', 'type' => 'datetime_immutable', 'columnName' => 'created_at']);
	$meta->mapField(['fieldName' => 'correlationId', 'type' => 'string', 'length' => 255, 'nullable' => true, 'columnName' => 'correlation_id']);
	$meta->table['indexes'] = ['audit_log_action' => ['columns' => ['action']]];

	$command = new PrintLogSchemaCommand($em, $target ?? new SchemaConnection(), $tables);
	$command->setLocksDir(sys_get_temp_dir());

	$tester = new Symfony\Component\Console\Tester\CommandTester($command);
	$tester->execute([]);

	return $tester->getDisplay();
}


test('vypis zaklada uzivatele i databazi podle spojeni', function () {
	// Aplikace do ciloveho serveru nema pristup - proto se to vypisuje k rucnimu spusteni.
	$sql = printSchema([]);

	Assert::contains('CREATE EXTENSION IF NOT EXISTS timescaledb', $sql);
	Assert::contains('CREATE USER "pokladna"', $sql);
	Assert::contains('CREATE DATABASE "pokladna_cashdesk"', $sql);
	// heslo se nevypisuje
	Assert::contains('<heslo>', $sql);
});


test('cas ma v PostgreSQL cilu zonu', function () {
	// Zdroj ma UTC, ale sloupec to nerika; mover posila offset, takze cil ho musi umet
	// prijmout - jinak ho zahodi a po case z dat nepozna, v cem jsou.
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]]);

	Assert::contains('WITH TIME ZONE', $sql);
	Assert::notContains('WITHOUT TIME ZONE', $sql);
});


test('prazdne jmeno databaze se ve vypisu pozna', function () {
	// V konfiguraci je bezne prazdno (hodnotu doplnuje stage); CREATE DATABASE ""
	// by si nikdo nemusel vsimnout.
	$sql = printSchema([], new SchemaConnection(['dbname' => '', 'user' => '']));

	Assert::contains('<databaze>', $sql);
	Assert::contains('<uzivatel>', $sql);
});


test('tabulka se odvodi z entity a jede v dialektu cile', function () {
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]]);

	Assert::contains('CREATE TABLE audit_log', $sql);
	Assert::contains('action', $sql);
	Assert::contains('correlation_id', $sql);
	// PostgreSQL, ne MySQL: zadne backticky ani AUTO_INCREMENT
	Assert::notContains('`', $sql);
	Assert::notContains('AUTO_INCREMENT', $sql);
});


test('id se v cili negeneruje, veze ho mover', function () {
	// Kdyby si ho cil pridelil sam, ztrati se vazba na zdroj a opakovany beh po preruseni
	// by zapsal totez podruhe.
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]]);

	Assert::notContains('SERIAL', strtoupper($sql));
	Assert::notContains('GENERATED', strtoupper($sql));
});


test('cilova tabulka se da prejmenovat', function () {
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => 'audit_log_archive', 'hot' => null, 'retention' => null]]);

	Assert::contains('CREATE TABLE audit_log_archive', $sql);
});


test('s dobami se doplni hypertable, komprese a retence', function () {
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => '3 months', 'retention' => '13 months']]);

	Assert::contains("create_hypertable('audit_log', 'created_at')", $sql);
	Assert::contains("add_compression_policy('audit_log', INTERVAL '3 months')", $sql);
	Assert::contains("add_retention_policy('audit_log', INTERVAL '13 months')", $sql);
});


test('u hypertable je v primarnim klici i delici sloupec', function () {
	// TimescaleDB jinak tabulku rozdelit odmitne.
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => '3 months', 'retention' => null]]);

	Assert::match('~PRIMARY KEY\s*\(id, created_at\)~', $sql);
});


test('bez dob se hypertable nezaklada', function () {
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]]);

	Assert::notContains('create_hypertable', $sql);
	Assert::match('~PRIMARY KEY\s*\(id\)~', $sql);
});


test('na MySQL se zaklada s kodovanim, ktere pouzivame', function () {
	// utf8mb4_general_ci, ne unicode - musi sedet se zdrojovou databazi, jinak se
	// porovnavani retezcu chova v cili jinak nez tam, odkud data prisla.
	$target = new SchemaConnection(
		['dbname' => 'pokladna_logs', 'user' => 'pokladna'],
		new Doctrine\DBAL\Platforms\MySQL80Platform(),
	);

	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => '3 months', 'retention' => '13 months']], $target);

	Assert::contains('CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', $sql);
	// TimescaleDB je jen v PostgreSQL, na MySQL se nesmi objevit
	Assert::notContains('create_hypertable', $sql);
});


test('aplikace dostane jen cteni a zapis', function () {
	// Tohle je duvod celeho oddeleneho uloziste: kdo se dostane k aplikaci, nesmi umet
	// prepsat zaznamy o tom, co v ni delal. Vlastnikem databaze proto neni aplikace.
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]]);

	Assert::contains('GRANT SELECT, INSERT ON audit_log TO "pokladna";', $sql);
	Assert::contains('WITH OWNER = "<vlastnik>"', $sql);
	Assert::notContains('WITH OWNER = "pokladna"', $sql);

	foreach (['UPDATE', 'DELETE', 'TRUNCATE', 'GRANT ALL'] as $_privilege) {
		Assert::notContains("$_privilege ON", $sql, "aplikace nesmi dostat $_privilege");
	}
});


test('tabulka, kterou aplikace cist nema, dostane jen zapis', function () {
	// Auditni stopa: odvoz do ni jen zapisuje (z cile necte), takze pravo SELECT
	// neni k cemu a dokument slibuje, ze z aplikace pristupna neni.
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null, 'readable' => false]]);

	Assert::contains('GRANT INSERT ON audit_log TO "pokladna";', $sql);
	Assert::notContains('GRANT SELECT', $sql);
});


test('prava se udeluji az za tabulkami', function () {
	// GRANT na jeste neexistujici tabulku neprojde - kdo vypis pousti odshora dolu,
	// by se zastavil na chybe.
	$sql = printSchema([['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]]);

	Assert::true(strpos($sql, 'GRANT SELECT, INSERT ON audit_log') > strpos($sql, 'CREATE TABLE audit_log'));
});


test('jmeno uzivatele s pomlckou projde', function () {
	// Uzivatele se jmenuji podle projektu (sobitpokladna-local_cashdesk) a pomlcka
	// v neuvozenem identifikatoru je v PostgreSQL syntakticka chyba.
	$sql = printSchema(
		[['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]],
		new SchemaConnection(['dbname' => 'pokladna-local_cashdesk_logs', 'user' => 'pokladna-local_cashdesk']),
	);

	Assert::contains('CREATE USER "pokladna-local_cashdesk"', $sql);
	Assert::contains('TO "pokladna-local_cashdesk";', $sql);
});
