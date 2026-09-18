<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Entities\AuditLog;
use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use ADT\FancyAdmin\Tests\Fixtures\TestConnection;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use Doctrine\DBAL\Types\Types;
use Tester\Assert;

/**
 * AuditLogger - zapis do jednotne tabulky audit_log.
 *
 * Zapisuje se pres DBAL, ne pres ORM: auditni radek nema co delat v identity mape.
 * Delku IP, id i popisku aktera ovlada utocnik, takze se orezavaji na delku sloupce -
 * jinak by stacilo poslat dlouhou hlavicku a zaznam by do logu nedosel.
 */

require __DIR__ . '/bootstrap.php';


const ACTOR = ['id' => null, 'label' => null, 'data' => [], 'ip' => null, 'userAgent' => null];

/** @return array{0: AuditLogger, 1: TestConnection} */
function createLogger(array $entityClasses = [TestAuditLog::class]): array
{
	$em = new TestEntityManager($entityClasses);

	if ($entityClasses !== []) {
		$metadata = $em->getClassMetadata($entityClasses[0]);
		$metadata->setPrimaryTable(['name' => 'audit_log']);

		foreach (['action', 'outcome', 'createdAt', 'correlationId', 'createdById', 'createdByLabel', 'createdBy', 'sourceIp', 'userAgent', 'payload'] as $field) {
			$metadata->mapField([
				'fieldName' => $field,
				'type' => 'string',
				'columnName' => strtolower((string) preg_replace('~([a-z])([A-Z])~', '$1_$2', $field)),
			]);
		}
	}

	return [new AuditLogger($em), $em->getConnection()];
}

function trimValue(?string $value, int $length): ?string
{
	return new ReflectionMethod(AuditLogger::class, 'trim')->invoke(null, $value, $length);
}


test('nazvy vysledku akce', function () {
	// Podle nich se filtruji selhani napric typy udalosti.
	Assert::same('success', AuditLogger::OUTCOME_SUCCESS);
	Assert::same('failure', AuditLogger::OUTCOME_FAILURE);
});


test('zaznam jde do tabulky podle mapovani entity', function () {
	[$logger, $connection] = createLogger();

	$logger->log('auth.login', AuditLogger::OUTCOME_SUCCESS, new DateTimeImmutable('2026-03-01 12:00:00'), null, ACTOR, []);

	$insert = $connection->getLastInsert();
	Assert::same('audit_log', $insert['table']);
	Assert::same('auth.login', $insert['data']['action']);
	Assert::same('success', $insert['data']['outcome']);
});


test('cas, akter i payload se zapisi do spravnych sloupcu', function () {
	[$logger, $connection] = createLogger();
	$createdAt = new DateTimeImmutable('2026-03-01 12:00:00', new DateTimeZone('UTC'));

	$logger->log(
		'export.download',
		AuditLogger::OUTCOME_SUCCESS,
		$createdAt,
		'export-42',
		['id' => '15', 'label' => 'Jan Novak', 'data' => ['email' => 'jan@example.com'], 'ip' => '192.0.2.10', 'userAgent' => 'Mozilla/5.0'],
		['rows' => 10],
	);

	$data = $connection->getLastInsert()['data'];
	Assert::same($createdAt, $data['created_at']);
	Assert::same('export-42', $data['correlation_id']);
	Assert::same('15', $data['created_by_id']);
	Assert::same('Jan Novak', $data['created_by_label']);
	Assert::same(['email' => 'jan@example.com'], $data['created_by']);
	Assert::same('192.0.2.10', $data['source_ip']);
	Assert::same('Mozilla/5.0', $data['user_agent']);
	Assert::same(['rows' => 10], $data['payload']);
});


test('typy sloupcu se predavaji DBALu', function () {
	// Bez toho by se datum ulozilo jako retezec v lokalni zone a pole by se neprevedlo na JSON.
	[$logger, $connection] = createLogger();

	$logger->log('auth.login', 'success', new DateTimeImmutable(), null, ACTOR, []);

	Assert::same([
		'created_at' => Types::DATETIME_IMMUTABLE,
		'created_by' => Types::JSON,
		'payload' => Types::JSON,
	], $connection->getLastInsert()['types']);
});


test('prazdny akter i payload se ulozi jako NULL', function () {
	[$logger, $connection] = createLogger();

	$logger->log('auth.login', 'failure', new DateTimeImmutable(), null, ACTOR, []);

	$data = $connection->getLastInsert()['data'];
	Assert::null($data['created_by']);
	Assert::null($data['payload']);
	Assert::null($data['created_by_id']);
	Assert::null($data['created_by_label']);
	Assert::null($data['source_ip']);
	Assert::null($data['user_agent']);
	Assert::null($data['correlation_id']);
});


test('dlouha IP a identifikatory aktera se orezou na delku sloupce', function () {
	// Delku ovlada klient (X-Forwarded-For), nesmi rozbit insert.
	[$logger, $connection] = createLogger();

	$logger->log('auth.login', 'failure', new DateTimeImmutable(), null, [
		'id' => str_repeat('a', 300),
		'label' => str_repeat('b', 300),
		'data' => [],
		'ip' => str_repeat('1', 100),
		'userAgent' => str_repeat('u', 5000),
	], []);

	$data = $connection->getLastInsert()['data'];
	Assert::same(255, strlen($data['created_by_id']));
	Assert::same(255, strlen($data['created_by_label']));
	Assert::same(45, strlen($data['source_ip']));
	// User-Agent jde do TEXT sloupce, ten se neorezava.
	Assert::same(5000, strlen($data['user_agent']));
});


test('orezani pocita znaky, ne bajty', function () {
	Assert::same(str_repeat('ě', 45), trimValue(str_repeat('ě', 100), 45));
	Assert::same('kratke', trimValue('kratke', 45));
	Assert::null(trimValue(null, 45));
	Assert::same('', trimValue('', 45));
});


test('chybejici auditni entita se pozna srozumitelnou chybou', function () {
	// Projekt si entitu musi vytvorit sam z AuditLogTrait.
	[$logger] = createLogger([]);

	Assert::exception(
		fn() => $logger->log('auth.login', 'success', new DateTimeImmutable(), null, ACTOR, []),
		RuntimeException::class,
		'%A%' . AuditLog::class . '%A%',
	);
});


test('entita, ktera auditni rozhrani neimplementuje, se preskoci', function () {
	[$logger] = createLogger([TestIdentity::class]);

	Assert::exception(
		fn() => $logger->log('auth.login', 'success', new DateTimeImmutable(), null, ACTOR, []),
		RuntimeException::class,
	);
});


test('auditni entita se najde i mezi ostatnimi', function () {
	$em = new TestEntityManager([TestIdentity::class, TestAuditLog::class]);
	$metadata = $em->getClassMetadata(TestAuditLog::class);
	$metadata->setPrimaryTable(['name' => 'audit_log']);
	foreach (['action', 'outcome', 'createdAt', 'correlationId', 'createdById', 'createdByLabel', 'createdBy', 'sourceIp', 'userAgent', 'payload'] as $field) {
		$metadata->mapField(['fieldName' => $field, 'type' => 'string', 'columnName' => $field]);
	}

	new AuditLogger($em)->log('auth.login', 'success', new DateTimeImmutable(), null, ACTOR, []);

	Assert::count(1, $em->getConnection()->inserts);
});
