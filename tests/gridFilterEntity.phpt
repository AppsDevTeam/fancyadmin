<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestGridFilter;
use Tester\Assert;

/**
 * GridFilter - ulozeny filtr datagridu.
 */

require __DIR__ . '/bootstrap.php';


test('filtr patri gridu a ma nazev', function () {
	$filter = new TestGridFilter();

	$filter->setGrid('identityGrid')->setName('Aktivni obchodnici');

	Assert::same('identityGrid', $filter->getGrid());
	Assert::same('Aktivni obchodnici', $filter->getName());
});


test('hodnota filtru je pole a vychozi je prazdne', function () {
	$filter = new TestGridFilter();

	Assert::same([], $filter->getValue());

	$value = ['isActive' => true, 'role' => [1, 2]];
	Assert::same($value, $filter->setValue($value)->getValue());
	Assert::same([], $filter->setValue([])->getValue());
});


test('filtr muze patrit uctu', function () {
	$filter = new TestGridFilter();
	$account = new TestAccount('Firma');

	Assert::null($filter->getAccount());
	Assert::same($account, $filter->setAccount($account)->getAccount());
	Assert::null($filter->setAccount(null)->getAccount());
});


test('filtr si pamatuje, kdo a kdy ho zalozil', function () {
	$filter = new TestGridFilter();
	$createdAt = new DateTimeImmutable('2026-01-01 10:00:00');

	Assert::null($filter->getCreatedBy());
	Assert::same($createdAt, $filter->setCreatedAt($createdAt)->getCreatedAt());
});
