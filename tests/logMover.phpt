<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Log\LogMover;
use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use ADT\FancyAdmin\Tests\Fixtures\TestRequestLog;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use Tester\Assert;

/**
 * Odvoz logu do oddeleneho uloziste.
 *
 * Auditni stopa je jedina kopie zaznamu o tom, co se v systemu delo, takze porad
 * operaci je tady to podstatne: nejdriv zapis do cile, pak teprve mazani ve zdroji.
 * A kdyz zapis selze, nesmi ze zdroje zmizet nic - jinak by ztratu nikdo nedohledal,
 * protoze zaznam o ni byl prave v tom, co zmizelo.
 */

require __DIR__ . '/bootstrap.php';


/** Zdrojove spojeni: vraci pripravene davky a pamatuje si, co se smazalo. */
final class SourceConnection extends Doctrine\DBAL\Connection
{
	public array $deleted = [];

	/** @param list<list<array<string, mixed>>> $batches */
	public function __construct(private array $batches = [], private int $count = 0)
	{
	}

	public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
	{
		return array_shift($this->batches) ?? [];
	}

	public function fetchOne(string $query, array $params = [], array $types = []): mixed
	{
		return $this->count;
	}

	public function executeStatement(string $sql, array $params = [], array $types = []): int|string
	{
		$this->deleted[] = $params[0];

		return count($params[0]);
	}
}

/** Cilove spojeni: zapisuje do pameti, umi predstirat uz odvezene zaznamy i selhani. */
final class TargetConnection extends Doctrine\DBAL\Connection
{
	public array $inserted = [];
	public array $transactions = [];

	/** @param list<int> $alreadyMoved */
	public function __construct(private array $alreadyMoved = [], private bool $failOnInsert = false)
	{
	}

	public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
	{
		return $this->alreadyMoved;
	}

	public function insert(string $table, array $data, array $types = []): int|string
	{
		if ($this->failOnInsert) {
			throw new RuntimeException('cil je nedostupny');
		}

		$this->inserted[] = $data;

		return 1;
	}

	public function beginTransaction(): void
	{
		$this->transactions[] = 'begin';
	}

	public function commit(): void
	{
		$this->transactions[] = 'commit';
	}

	public function rollBack(): void
	{
		$this->transactions[] = 'rollback';
	}
}

function auditRow(int $id, string $action = 'login'): array
{
	return [
		'id' => $id,
		'action' => $action,
		'outcome' => 'success',
		'created_at' => '2026-03-01 12:00:00',
		'correlation_id' => null,
		'created_by_id' => '15',
		'created_by_label' => 'Jan Novak',
		'created_by' => '{"email":"jan@example.com"}',
		'source_ip' => '192.0.2.10',
		'user_agent' => 'Mozilla/5.0',
		'payload' => null,
	];
}

function createMover(SourceConnection $source, TargetConnection $target, ?array $config = null): LogMover
{
	$em = new TestEntityManager([TestAuditLog::class, TestRequestLog::class], $source);
	$em->getClassMetadata(TestAuditLog::class)->setPrimaryTable(['name' => 'audit_log']);
	$em->getClassMetadata(TestRequestLog::class)->setPrimaryTable(['name' => 'request_log']);

	return new LogMover($em, $target, $config ?? [
		['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null],
	]);
}

/** @return array{0: int, 1: string} pocet odvezenych a souhrn chyb */
function runMover(LogMover $mover, array $options = []): array
{
	$result = $mover->moveAll($options['batchSize'] ?? LogMover::BATCH_SIZE, $options['limit'] ?? 0);

	return [array_sum($result['moved']), implode(', ', array_keys($result['errors']))];
}


test('zaznam se odveze i se svym puvodnim id', function () {
	// Id se veze s sebou, aby sel zaznam dohledat zpatky a aby odkazy mezi odvezenymi
	// tabulkami dal sedely. Predpokladem je jedna cilova databaze na zdroj.
	$source = new SourceConnection([[auditRow(42)]]);
	$target = new TargetConnection();

	runMover(createMover($source, $target));

	Assert::count(1, $target->inserted);
	Assert::same(42, $target->inserted[0]['id']);
	Assert::same('login', $target->inserted[0]['action']);
});


test('cas se do cile posila s vyslovnou UTC zonou', function () {
	// Cil muze mit sloupec se zonou; bez offsetu by si hodnotu vylozil podle sve vlastni
	// a zaznamy by prestaly sedet s ostatnimi logy - tise, nic by nespadlo.
	$source = new SourceConnection([[auditRow(1)]]);
	$target = new TargetConnection();

	runMover(createMover($source, $target));

	Assert::same('2026-03-01 12:00:00+00:00', $target->inserted[0]['created_at']);
});


test('maze se az po zapisu do cile', function () {
	$source = new SourceConnection([[auditRow(1), auditRow(2)]]);
	$target = new TargetConnection();

	runMover(createMover($source, $target));

	Assert::same(['begin', 'commit'], $target->transactions);
	Assert::same([[1, 2]], $source->deleted);
});


test('kdyz zapis do cile selze, ze zdroje nezmizi nic', function () {
	// Tohle je ta vec, kvuli ktere to cele je: ztrata auditniho zaznamu se nijak
	// neprojevi, protoze zaznam o ni byl prave v tom, co zmizelo.
	$source = new SourceConnection([[auditRow(1)]]);
	$target = new TargetConnection(failOnInsert: true);

	[, $errors] = runMover(createMover($source, $target));

	Assert::same([], $source->deleted);
	Assert::same(['begin', 'rollback'], $target->transactions);
	// chyba se vrati volajicimu - command ji vypise, job na ni spadne
	Assert::same('audit_log', $errors);
});


test('uz odvezeny zaznam se nezapise podruhe, jen se uklidi ze zdroje', function () {
	// Stav po behu preruzenem mezi zapisem a mazanim.
	$source = new SourceConnection([[auditRow(7), auditRow(8)]]);
	$target = new TargetConnection(alreadyMoved: [7]);

	[$moved] = runMover(createMover($source, $target));

	Assert::count(1, $target->inserted);
	Assert::same(8, $target->inserted[0]['id']);
	// smazat se musi obe: sedmicka uz v cili je
	Assert::same([[7, 8]], $source->deleted);
	// sedmicka uz v cili byla, takze se do poctu odvezenych nezapocitala
	Assert::same(1, $moved);
});


test('vozi se po davkach, dokud je co', function () {
	$source = new SourceConnection([[auditRow(1)], [auditRow(2)], []]);
	$target = new TargetConnection();

	[$moved] = runMover(createMover($source, $target));

	Assert::count(2, $target->inserted);
	Assert::same([[1], [2]], $source->deleted);
	Assert::same(2, $moved);
});


test('limit zastavi beh drive', function () {
	// Aby nocni odvoz nezablokoval databazi na hodiny, kdyz se nahromadi.
	$source = new SourceConnection([[auditRow(1)], [auditRow(2)], [auditRow(3)]]);
	$target = new TargetConnection();

	runMover(createMover($source, $target), ['limit' => 1]);

	Assert::count(1, $target->inserted);
});


test('spocitat cekajici zaznamy jde bez sahnuti na data', function () {
	$source = new SourceConnection(count: 128);
	$target = new TargetConnection();

	$mover = createMover($source, $target);

	Assert::same(128, $mover->countWaiting(TestAuditLog::class));
	Assert::same([], $target->inserted);
	Assert::same([], $source->deleted);
});


test('odvazi se vsechny tabulky z konfigurace', function () {
	// Mover neni jen na auditni stopu: stejne to potrebuje change_log i provozni logy,
	// ktere se maji drzet dlouho, ale ne v provozni databazi.
	$source = new SourceConnection([[auditRow(1)], [], [auditRow(2)], []]);
	$target = new TargetConnection();
	$config = [
		['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null],
		['entity' => TestRequestLog::class, 'table' => 'request_log_archive', 'hot' => null, 'retention' => null],
	];

	$result = createMover($source, $target, $config)->moveAll();

	Assert::count(2, $target->inserted);
	Assert::same([], $result['errors']);
	// vysledek je klicovany zdrojovou tabulkou, kazda ma svou
	Assert::same(['audit_log' => 1, 'request_log' => 1], $result['moved']);
});


test('nedostupna tabulka shodi jen svuj radek', function () {
	$source = new SourceConnection([[auditRow(1)]]);
	$target = new TargetConnection(failOnInsert: true);
	$config = [['entity' => TestAuditLog::class, 'table' => null, 'hot' => null, 'retention' => null]];

	$result = createMover($source, $target, $config)->moveAll();

	Assert::same(['audit_log'], array_keys($result['errors']));
	Assert::same([], $source->deleted);
});
