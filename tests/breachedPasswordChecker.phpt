<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\BreachedPasswordChecker;
use Tester\Assert;

/**
 * Kontrola uniklych hesel.
 *
 * Testuje se lokalni seznam nejcastejsich hesel; dotaz na api.pwnedpasswords.com se
 * zamerne nespousti, aby testy nezavisely na siti (a aby neodesilaly nic ven).
 */

require __DIR__ . '/bootstrap.php';


/** @return mixed */
function callPrivate(BreachedPasswordChecker $checker, string $method, ...$args)
{
	$reflection = new ReflectionMethod($checker, $method);
	return $reflection->invoke($checker, ...$args);
}

function readPrivate(BreachedPasswordChecker $checker, string $property): mixed
{
	return new ReflectionProperty($checker, $property)->getValue($checker);
}


test('seznam nejcastejsich hesel je soucasti balicku', function () {
	$file = __DIR__ . '/../src/Model/Security/assets/common-passwords.txt';

	Assert::true(is_file($file));
	Assert::true(filesize($file) > 0);
});


test('heslo ze seznamu se pozna', function () {
	$checker = new BreachedPasswordChecker();

	Assert::true(callPrivate($checker, 'isInCommonList', 'password'));
	Assert::true(callPrivate($checker, 'isInCommonList', '123456'));
	Assert::true(callPrivate($checker, 'isInCommonList', 'qwerty'));
});


test('velikost pismen nerozhoduje', function () {
	$checker = new BreachedPasswordChecker();

	Assert::true(callPrivate($checker, 'isInCommonList', 'PASSWORD'));
	Assert::true(callPrivate($checker, 'isInCommonList', 'PaSsWoRd'));
});


test('heslo mimo seznam neprojde jako unikle', function () {
	$checker = new BreachedPasswordChecker();

	Assert::false(callPrivate($checker, 'isInCommonList', 'Zc7#vQ92-tL!mXr4'));
	Assert::false(callPrivate($checker, 'isInCommonList', bin2hex(random_bytes(16))));
});


test('seznam se cte ze souboru jen jednou', function () {
	$checker = new BreachedPasswordChecker();

	Assert::null(readPrivate($checker, 'commonPasswords'));

	callPrivate($checker, 'isInCommonList', 'password');
	$loaded = readPrivate($checker, 'commonPasswords');

	Assert::type('array', $loaded);
	Assert::true(count($loaded) > 1000);

	callPrivate($checker, 'isInCommonList', 'qwerty');
	Assert::same($loaded, readPrivate($checker, 'commonPasswords'));
});


test('chybejici soubor kontrolu neshodi', function () {
	// Fail-open je tady zamer: bez seznamu se heslo proste neoznaci, ale prihlaseni bezi dal.
	$checker = new BreachedPasswordChecker();
	new ReflectionProperty($checker, 'commonPasswordsFile')->setValue($checker, __DIR__ . '/neexistuje.txt');

	Assert::false(callPrivate($checker, 'isInCommonList', 'password'));
	Assert::same([], readPrivate($checker, 'commonPasswords'));
});


test('heslo ze seznamu se vyhodnoti bez dotazu do site', function () {
	// isBreached() ma OR se zkracenym vyhodnocenim - lokalni shoda utne HIBP dotaz,
	// takze test nesaha na sit. Kdyby se poradi otocilo, tohle to odhali.
	$checker = new BreachedPasswordChecker();

	$start = microtime(true);
	$result = $checker->isBreached('password');
	$elapsed = microtime(true) - $start;

	Assert::true($result);
	Assert::true($elapsed < 0.4, 'isBreached() u hesla ze seznamu nesmi cekat na sitovy dotaz');
});


test('prazdne heslo je v seznamu kvuli koncovemu radku souboru', function () {
	// Soubor konci novym radkem, takze v seznamu je i prazdny retezec. Prakticky to nevadi
	// (prazdne heslo nikdy nema projit), ale je dobre o tom vedet.
	Assert::true(callPrivate(new BreachedPasswordChecker(), 'isInCommonList', ''));
});
