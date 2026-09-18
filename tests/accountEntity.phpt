<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use Tester\Assert;

/**
 * AccountTrait - ucet a jeho podrizene ucty.
 */

require __DIR__ . '/bootstrap.php';


test('ucet ma nazev', function () {
	$account = new TestAccount('Firma');

	Assert::same('Firma', $account->getName());
	Assert::same('Jina firma', $account->setName('Jina firma')->getName());
});


test('ucet nemusi mit rodice', function () {
	$account = new TestAccount('Firma');

	Assert::null($account->getParent());
	Assert::same([], $account->getSubaccounts());
});


test('podrizene ucty znaji sveho rodice', function () {
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka');
	$sklad = new TestAccount('Sklad');

	$firma->addSubaccount($pobocka)->addSubaccount($sklad);

	Assert::same([$pobocka, $sklad], $firma->getSubaccounts());
	Assert::same($firma, $pobocka->getParent());
	Assert::same($firma, $sklad->getParent());
});


test('rodic jde nastavit i odebrat', function () {
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka', parent: $firma);

	Assert::same($firma, $pobocka->getParent());
	Assert::null($pobocka->setParent(null)->getParent());
});


test('hierarchie je jen jednourovnova pro cteni', function () {
	// getSubaccounts() vraci jen prime potomky - vnuci se do vysledku nepromitaji.
	$firma = new TestAccount('Firma');
	$pobocka = new TestAccount('Pobocka');
	$sklad = new TestAccount('Sklad');

	$firma->addSubaccount($pobocka);
	$pobocka->addSubaccount($sklad);

	Assert::same([$pobocka], $firma->getSubaccounts());
	Assert::same([$sklad], $pobocka->getSubaccounts());
});
