<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\ApiKeyHasher;
use Tester\Assert;

/**
 * Generovani a hashovani API klicu.
 *
 * V databazi lezi jen otisk, citelny klic uvidi uzivatel jednou pri vytvoreni - overeni
 * pak znamena spocitat otisk prichoziho klice a najit shodu.
 */

require __DIR__ . '/bootstrap.php';


test('klic ma delku z konstanty a jen alfanumericke znaky', function () {
	Assert::same(32, ApiKeyHasher::RAW_KEY_LENGTH);

	$key = ApiKeyHasher::generateRawKey();

	Assert::same(ApiKeyHasher::RAW_KEY_LENGTH, strlen($key));
	Assert::match('#^[0-9a-zA-Z]{32}$#', $key);
});


test('kazde volani da jiny klic', function () {
	// Nahodnost se otestovat neda, ale kolize ve stovce klicu by rovnou znamenala,
	// ze se negeneruje vubec nic.
	$keys = [];
	for ($i = 0; $i < 100; $i++) {
		$keys[] = ApiKeyHasher::generateRawKey();
	}

	Assert::count(100, array_unique($keys));
});


test('otisk je sha256 a je deterministicky', function () {
	Assert::same(hash('sha256', 'tajny-klic'), ApiKeyHasher::hash('tajny-klic'));
	Assert::same(ApiKeyHasher::hash('tajny-klic'), ApiKeyHasher::hash('tajny-klic'));
	Assert::same(64, strlen(ApiKeyHasher::hash('tajny-klic')));
	Assert::match('#^[0-9a-f]{64}$#', ApiKeyHasher::hash('tajny-klic'));
});


test('jiny klic da jiny otisk', function () {
	Assert::notSame(ApiKeyHasher::hash('a'), ApiKeyHasher::hash('b'));
	// Otisk nesmi zalezet na velikosti pismen jen "skoro" - klic je case sensitive.
	Assert::notSame(ApiKeyHasher::hash('Abc'), ApiKeyHasher::hash('abc'));
});


test('prazdny i binarni vstup projde', function () {
	Assert::same(hash('sha256', ''), ApiKeyHasher::hash(''));
	Assert::same(hash('sha256', "\0\xFF"), ApiKeyHasher::hash("\0\xFF"));
});


test('vygenerovany klic se da rovnou zahashovat a porovnat', function () {
	$raw = ApiKeyHasher::generateRawKey();

	Assert::true(hash_equals(ApiKeyHasher::hash($raw), ApiKeyHasher::hash($raw)));
	Assert::false(hash_equals(ApiKeyHasher::hash($raw), ApiKeyHasher::hash(ApiKeyHasher::generateRawKey())));
});
