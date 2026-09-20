<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\RequestLogger;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use Nette\Application\Responses\TextResponse;
use ADT\FancyAdmin\Tests\Fixtures\TestPresenter;
use Tester\Assert;

/**
 * RequestLogger - zapis pozadavku do tabulky request_log.
 *
 * Testuje se to, co nepotrebuje databazi: vlastni sloupce projektu a podminka, kdy se
 * pozadavek vubec neloguje.
 */

require __DIR__ . '/bootstrap.php';


function extraLogData(): array
{
	return new ReflectionProperty(RequestLogger::class, 'extraLogData')->getValue();
}

function resetRequestLogger(): void
{
	new ReflectionProperty(RequestLogger::class, 'extraLogData')->setValue(null, []);
	RequestLogger::$apiKeyId = null;
	RequestLogger::$logResponse = false;
}

function createLogger(bool $isLoggedIn): RequestLogger
{
	return new RequestLogger(
		['driver' => 'pdo_sqlite', 'memory' => true],
		new TestSecurityUser(isLoggedIn: $isLoggedIn, identity: new TestIdentity()),
		new SensitiveDataSanitizer(),
	);
}


test('vlastni sloupce se pridavaji', function () {
	resetRequestLogger();

	Assert::same([], extraLogData());

	RequestLogger::addValue('device_id', 'abc');
	RequestLogger::addValue('correlation_id', 'export-42');

	Assert::same(['device_id' => 'abc', 'correlation_id' => 'export-42'], extraLogData());
});


test('stejny sloupec se prepise', function () {
	resetRequestLogger();

	RequestLogger::addValue('device_id', 'prvni');
	RequestLogger::addValue('device_id', 'druhy');

	Assert::same(['device_id' => 'druhy'], extraLogData());
});


test('hodnota sloupce muze byt cokoliv', function () {
	resetRequestLogger();

	RequestLogger::addValue('pocet', 42);
	RequestLogger::addValue('priznak', true);
	RequestLogger::addValue('nic', null);

	Assert::same(['pocet' => 42, 'priznak' => true, 'nic' => null], extraLogData());
});


test('prepinace logovani jsou ve vychozim stavu vypnute', function () {
	resetRequestLogger();

	Assert::false(RequestLogger::$logResponse);
	Assert::null(RequestLogger::$apiKeyId);
});


test('anonymni pozadavek se neloguje', function () {
	// Bez toho by kazdy pozadavek neprihlaseneho navstevnika zakladal radek v request_log.
	resetRequestLogger();
	$logger = createLogger(isLoggedIn: false);

	// Databaze v testu neexistuje - kdyby se logovalo, spadne to na spojeni.
	Assert::noError(fn() => $logger->logRequest(new TestPresenter(), new TextResponse('ok')));
});


test('pozadavek s API klicem se loguje i bez prihlaseni', function () {
	resetRequestLogger();
	RequestLogger::$apiKeyId = 7;

	$logDir = sys_get_temp_dir() . '/fancyadmin-tests/' . getmypid() . '/request-logger';
	@mkdir($logDir, 0777, recursive: true);
	Tester\Helpers::purge($logDir);
	Tracy\Debugger::$logDirectory = $logDir;

	// Logovani se spusti (nevratilo se hned) a spadne az na nesestavenem pozadavku.
	// Selhani logovani nikdy nesmi shodit request - zaloguje se a jede se dal.
	Assert::noError(fn() => createLogger(isLoggedIn: false)->logRequest(new TestPresenter(), new TextResponse('ok')));
	Assert::contains('RequestLogger selhal', file_get_contents($logDir . '/critical.log'));

	resetRequestLogger();
});


test('hloubka JSON je omezena limitem MySQL', function () {
	// MySQL pusti do sloupce json 100 urovni, PHP parsuje do 512 - telo mezi temito limity
	// by proslo aplikaci a rozbilo se az pri insertu.
	Assert::same(100, new ReflectionClassConstant(RequestLogger::class, 'MAX_JSON_COLUMN_DEPTH')->getValue());
});


test('kdyz selze telo, nezustane po pozadavku ani hlavicka', function () {
	// Hlavicka a telo jsou dva inserty, ale jedna transakce. Bez ni je mezi nimi okamzik,
	// kdy rodic uz v databazi je a telo jeste ne - a soubezny odvoz logu (move-logs) ho
	// v tu chvili muze odvezt a smazat, takze telo spadne na cizim klici:
	//   Cannot add or update a child row: a foreign key constraint fails
	// Tady se totez nasimuluje chybejici tabulkou tela: kdyz zapis tela selze, nesmi po
	// pozadavku zustat osirela hlavicka.
	$soubor = tempnam(sys_get_temp_dir(), 'requestlog') . '.sqlite';
	$dbParams = ['driver' => 'pdo_sqlite', 'path' => $soubor];

	$connection = Doctrine\DBAL\DriverManager::getConnection($dbParams);
	$connection->executeStatement('CREATE TABLE request_log (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT)');
	// request_log_body schvalne neexistuje

	$logger = new RequestLogger($dbParams, new TestSecurityUser(isLoggedIn: true, identity: new TestIdentity()), new SensitiveDataSanitizer());
	$zapis = new ReflectionMethod(RequestLogger::class, 'writeLog');

	Assert::exception(
		fn() => $zapis->invoke($logger, $connection, ['created_at' => '2026-09-20 12:00:00'], ['headers' => null]),
		Throwable::class,
	);

	Assert::same(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM request_log'), 'hlavicka se musela vratit zpet');

	@unlink($soubor);
});
