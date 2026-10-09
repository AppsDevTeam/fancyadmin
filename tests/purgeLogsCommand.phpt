<?php

declare(strict_types=1);

use ADT\FancyAdmin\Console\PurgeLogsCommand;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestRequestLog;
use Tester\Assert;

/**
 * Prikaz, ktery maze logy starsi nez jejich retencni doba.
 *
 * Retencni doby jsou slib vuci zakaznikovi i regulatorovi, takze se hlida, ze se
 * hranice pocita podle konfigurace a ze se maze po davkach - jedno velke DELETE
 * nad milionovou tabulkou drzi zamky a utika s nim replika.
 */

require __DIR__ . '/bootstrap.php';


/**
 * Spojeni, ktere misto mazani pocita davky a vraci predem dane pocty smazanych radku.
 */
final class PurgeConnection extends Doctrine\DBAL\Connection
{
	public array $statements = [];

	/** @param list<int> $deletedPerBatch */
	public function __construct(private array $deletedPerBatch = [0], private int $count = 0)
	{
	}

	public function executeStatement(string $sql, array $params = [], array $types = []): int|string
	{
		$this->statements[] = ['sql' => $sql, 'params' => $params];

		return array_shift($this->deletedPerBatch) ?? 0;
	}

	public function fetchOne(string $query, array $params = [], array $types = []): mixed
	{
		$this->statements[] = ['sql' => $query, 'params' => $params];

		return $this->count;
	}
}

function createCommand(array $config, PurgeConnection $connection): PurgeLogsCommand
{
	$em = new TestEntityManager([TestRequestLog::class], $connection);
	$metadata = $em->getClassMetadata(TestRequestLog::class);
	$metadata->setPrimaryTable(['name' => 'request_log']);
	$metadata->mapField(['fieldName' => 'createdAt', 'type' => 'datetime_immutable', 'columnName' => 'created_at']);

	$command = new PurgeLogsCommand($em, $config);
	// zamek si jinak sahne na neinicializovanou cestu, v ostrem behu ji dosadi DI
	$command->setLocksDir(sys_get_temp_dir());

	return $command;
}

/** @return array{0: int, 1: string} navratovy kod a vypis */
function runCommand(PurgeLogsCommand $command, array $input = []): array
{
	$tester = new Symfony\Component\Console\Tester\CommandTester($command);
	$tester->execute($input);

	return [$tester->getStatusCode(), $tester->getDisplay()];
}


test('prazdna konfigurace nic nemaze', function () {
	$connection = new PurgeConnection();
	[$status] = runCommand(createCommand([], $connection));

	Assert::same(0, $status);
	Assert::same([], $connection->statements);
});


test('maze podle retencni doby z konfigurace', function () {
	$connection = new PurgeConnection([5, 0]);
	$config = [['entity' => TestRequestLog::class, 'retention' => '6 months']];

	runCommand(createCommand($config, $connection));

	$first = $connection->statements[0];
	Assert::contains('DELETE FROM request_log', $first['sql']);
	Assert::contains('created_at <', $first['sql']);

	// hranice musi sedet na dnesek minus retence, ne na nejakou konstantu v kodu
	$expected = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('-6 months');
	Assert::same($expected->format('Y-m-d H'), substr($first['params'][0], 0, 13));
});


test('maze po davkach, dokud neco ubyva', function () {
	// Jedno velke DELETE by drzelo zamky pres celou tabulku.
	$connection = new PurgeConnection([10000, 10000, 137, 0]);
	$config = [['entity' => TestRequestLog::class, 'retention' => '1 month']];

	[$status, $display] = runCommand(createCommand($config, $connection));

	Assert::same(0, $status);
	Assert::count(4, $connection->statements);
	Assert::contains('LIMIT 10000', $connection->statements[0]['sql']);
	// 10000 + 10000 + 137
	Assert::contains('20137', $display);
});


test('velikost davky jde prepsat parametrem', function () {
	$connection = new PurgeConnection([0]);
	$config = [['entity' => TestRequestLog::class, 'retention' => '1 month']];

	runCommand(createCommand($config, $connection), ['--batch-size' => '500']);

	Assert::contains('LIMIT 500', $connection->statements[0]['sql']);
});


test('dry-run jen spocita, co by smazal', function () {
	$connection = new PurgeConnection(count: 42);
	$config = [['entity' => TestRequestLog::class, 'retention' => '6 months']];

	[$status, $display] = runCommand(createCommand($config, $connection), ['--dry-run' => true]);

	Assert::same(0, $status);
	Assert::contains('SELECT COUNT(*)', $connection->statements[0]['sql']);
	Assert::contains('42', $display);
	// nic se nesmazalo
	Assert::count(1, $connection->statements);
});


test('neznama entita shodi jen svuj radek, ostatni se domazou', function () {
	// Preklep v konfiguraci nesmi zastavit mazani zbytku - databaze by rostla dal vsude.
	$connection = new PurgeConnection([7, 0]);
	$config = [
		['entity' => 'App\Neexistuje', 'retention' => '6 months'],
		['entity' => TestRequestLog::class, 'retention' => '6 months'],
	];

	[$status, $display] = runCommand(createCommand($config, $connection));

	Assert::same(1, $status);
	Assert::contains('CHYBA', $display);
	Assert::contains('DELETE FROM request_log', $connection->statements[0]['sql']);
});
