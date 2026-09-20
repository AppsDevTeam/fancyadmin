<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\ApiLogger;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use Doctrine\DBAL\DriverManager;
use Tester\Assert;

/**
 * ApiLogger - zapis volani ciziho rozhrani, kde aplikace vystupuje jako klient.
 *
 * Podstatne je, ze zaznam prezije i to, kvuli cemu se loguje: pise se vlastnim spojenim,
 * takze ho rollback ORM transakce nesmete, a selhani zapisu nesmi shodit volani samotne.
 */

require __DIR__ . '/bootstrap.php';


function apiLogDbParams(): array
{
	static $path = null;
	$path ??= tempnam(sys_get_temp_dir(), 'apilog') . '.sqlite';

	return ['driver' => 'pdo_sqlite', 'path' => $path];
}

function createApiLogTable(): Doctrine\DBAL\Connection
{
	$connection = DriverManager::getConnection(apiLogDbParams());
	$connection->executeStatement('DROP TABLE IF EXISTS api_log');
	$connection->executeStatement(
		'CREATE TABLE api_log (
			type TEXT, environment TEXT, endpoint_url TEXT, request TEXT, response TEXT,
			http_status_code INTEGER, error_message TEXT, duration_ms INTEGER,
			account_id INTEGER, created_at TEXT, document_id INTEGER
		)',
	);

	return $connection;
}

function createApiLogger(): ApiLogger
{
	return new ApiLogger(apiLogDbParams(), new SensitiveDataSanitizer());
}


test('zaznam nese, co se volalo a jak to dopadlo', function () {
	$connection = createApiLogTable();

	createApiLogger()->log('eet_submission', 'playground', 'https://eet.cz', '<Trzba/>', '<Potvrzeni/>', 200, 42, 7);

	$row = $connection->fetchAssociative('SELECT * FROM api_log');
	Assert::same('eet_submission', $row['type']);
	Assert::same('playground', $row['environment']);
	Assert::same('https://eet.cz', $row['endpoint_url']);
	Assert::same('<Trzba/>', $row['request']);
	Assert::same('<Potvrzeni/>', $row['response']);
	Assert::same(200, $row['http_status_code']);
	Assert::same(42, $row['duration_ms']);
	Assert::same(7, $row['account_id']);
});


test('cas se uklada v UTC', function () {
	// Zaznamy se odvazi do spolecneho uloziste vedle ostatnich logu - lokalni cas
	// by je proti nim posunul, aniz by cokoliv spadlo.
	$connection = createApiLogTable();
	$before = new DateTimeImmutable('now', new DateTimeZone('UTC'));

	createApiLogger()->log('eet_submission', null, null, null, null, null, null);

	$createdAt = new DateTimeImmutable($connection->fetchOne('SELECT created_at FROM api_log'), new DateTimeZone('UTC'));
	Assert::true(abs($createdAt->getTimestamp() - $before->getTimestamp()) < 60);
});


test('obsah se sanitizuje', function () {
	// Protistrana muze vratit neco, co se do logu ulozit nesmi - typicky cislo karty
	// v odpovedi platebniho rozhrani.
	$connection = createApiLogTable();
	$pan = '4111111111111111';

	createApiLogger()->log('eet_submission', null, null, "<Trzba pan=\"$pan\"/>", "<Odpoved>$pan</Odpoved>", null, null, errorMessage: "timeout pri $pan");

	$row = $connection->fetchAssociative('SELECT * FROM api_log');
	Assert::notContains($pan, $row['request']);
	Assert::notContains($pan, $row['response']);
	Assert::notContains($pan, $row['error_message']);
});


test('projekt si muze pridat vlastni sloupec', function () {
	$connection = createApiLogTable();

	createApiLogger()->log('eet_submission', null, null, null, null, null, null, extraValues: ['document_id' => 15]);

	Assert::same(15, $connection->fetchOne('SELECT document_id FROM api_log'));
});


test('vlastni sloupce neprepisou systemove', function () {
	$connection = createApiLogTable();

	createApiLogger()->log('eet_submission', null, null, null, null, null, null, extraValues: ['type' => 'podvrzeny']);

	Assert::same('eet_submission', $connection->fetchOne('SELECT type FROM api_log'));
});


test('kdyz zapis selze, volani to neshodi', function () {
	// Tabulka neexistuje - logger to musi spolknout, protoze operace, o ktere vypovida,
	// uz probehla a shazovat ji kvuli logu je horsi nez o log prijit.
	DriverManager::getConnection(apiLogDbParams())->executeStatement('DROP TABLE IF EXISTS api_log');

	Assert::noError(function () {
		createApiLogger()->log('eet_submission', null, null, null, null, null, null);
	});
});
