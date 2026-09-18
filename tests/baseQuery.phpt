<?php

declare(strict_types=1);

use ADT\DoctrineComponents\QueryObject\Filters\IsActiveFilter;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Tests\Fixtures\Queries\InvoiceQuery;
use ADT\FancyAdmin\Tests\Fixtures\Queries\OrderQuery;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use Tester\Assert;

/**
 * BaseQueryTrait - spolecny zaklad vsech dotazu balicku.
 *
 * Bezpecnostni a uctovy filtr se pridavaji automaticky pri init(); kdo je chce vypnout,
 * musi si o to rict - proto maji pojmenovane klice a daji se odebrat.
 */

require __DIR__ . '/bootstrap.php';


function createQuery(bool $isLoggedIn = true): OrderQuery
{
	$query = new OrderQuery();
	$query->setSecurityUser(new TestSecurityUser(isLoggedIn: $isLoggedIn, identity: new TestIdentity()));
	$query->init();

	return $query;
}


test('nazvy filtru', function () {
	Assert::same('securityFilter', BaseQuery::SECURITY_FILTER);
	Assert::same('accountFilter', BaseQuery::ACCOUNT_FILTER);
});


test('init prida bezpecnostni i uctovy filtr', function () {
	$query = createQuery();

	Assert::true($query->initCalled);
	Assert::same([BaseQuery::SECURITY_FILTER, BaseQuery::ACCOUNT_FILTER], array_keys($query->filter));
});


test('filtry jdou vypnout jednotlive', function () {
	$query = createQuery();

	Assert::same($query, $query->disableSecurityFilter());
	Assert::same([BaseQuery::ACCOUNT_FILTER], array_keys($query->filter));

	Assert::same($query, $query->disableAccountFilter());
	Assert::same([], array_keys($query->filter));
});


test('vypnuti neexistujiciho filtru nic nerozbije', function () {
	$query = createQuery();
	$query->disableSecurityFilter();

	Assert::noError($query->disableSecurityFilter(...));
});


test('dotaz s filtrem aktivity ho prida navic', function () {
	$query = new InvoiceQuery();
	$query->setSecurityUser(new TestSecurityUser());
	$query->init();

	Assert::same([IsActiveFilter::IS_ACTIVE_FILTER, BaseQuery::ACCOUNT_FILTER], array_keys($query->filter));
});


test('hledani podle id filtr aktivity vypina', function () {
	// Detail zaznamu se ma otevrit i po deaktivaci - jinak by odkaz prestal fungovat.
	$query = new InvoiceQuery();
	$query->setSecurityUser(new TestSecurityUser());
	$query->init();

	$query->byId(5);

	Assert::true($query->isActiveFilterDisabled);
	Assert::same(['column' => 'id', 'value' => 5, 'mode' => QueryObjectByMode::AUTO], $query->byCalls[0]);
});


test('u dotazu bez filtru aktivity se nic nevypina', function () {
	$query = createQuery();

	$query->byId(5);

	Assert::false($query->isActiveFilterDisabled);
});


test('entitni trida se odvodi z nazvu dotazu', function () {
	Assert::same('ADT\FancyAdmin\Tests\Fixtures\Entities\Order', new OrderQuery()->getEntityClass());
	Assert::same('ADT\FancyAdmin\Tests\Fixtures\Entities\Invoice', new InvoiceQuery()->getEntityClass());
});


test('vyloucena id se prevedou na NOT IN', function () {
	$query = createQuery();

	$query->byIdNot([1, 2, 3]);

	Assert::same(['column' => 'id', 'value' => [1, 2, 3], 'mode' => QueryObjectByMode::NOT_IN_ARRAY], $query->byCalls[0]);
});


test('jedno vyloucene id se zabali do pole', function () {
	$query = createQuery();

	$query->byIdNot(7);

	Assert::same([7], $query->byCalls[0]['value']);
});


test('prazdne vylouceni dotaz nemeni', function () {
	// Bez teto pojistky by z prazdneho pole vzniklo "NOT IN ()" a dotaz by nevratil nic.
	$query = createQuery();

	Assert::same($query, $query->byIdNot([]));
	Assert::same($query, $query->byIdNot(null));
	Assert::same($query, $query->byIdNot([0, null]));
	Assert::same([], $query->byCalls);
});


test('vlastni filtr jde pridat pojmenovany i anonymni', function () {
	$query = createQuery();
	$query->disableSecurityFilter();
	$query->disableAccountFilter();

	$method = new ReflectionMethod($query, 'addFilter');
	$method->invoke($query, fn() => null, 'mujFiltr');
	$method->invoke($query, fn() => null);

	Assert::same(['mujFiltr', 0], array_keys($query->filter));
});


test('vychozi parovani je id => name', function () {
	Assert::same(['value' => 'name', 'key' => 'id'], createQuery()->fetchPairs());
	Assert::same(['value' => 'title', 'key' => 'uuid'], createQuery()->fetchPairs('title', 'uuid'));
});


test('prihlaseny uzivatel se da precist zpatky', function () {
	$securityUser = new TestSecurityUser();
	$query = new OrderQuery();

	Assert::same($query, $query->setSecurityUser($securityUser));
	Assert::same($securityUser, new ReflectionMethod($query, 'getSecurityUser')->invoke($query));
});
