<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Tests\Fixtures\Queries\ShipmentQuery;
use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Tester\Assert;

/**
 * DefaultFilters - výchozí bezpečnostní a účtový filtr dotazů.
 *
 * Při více tenantech filtr omezuje data na účty uživatele. Single-tenant entity vazbu
 * na účet mít nemusí, takže filtr na účet nesmí sáhnout vůbec - jinak by dotaz spadl na DQL.
 */

require __DIR__ . '/bootstrap.php';


function createQueryBuilder(): QueryBuilder
{
	// Filtr jen skládá DQL, EntityManager nepotřebuje
	$qb = new ReflectionClass(QueryBuilder::class)->newInstanceWithoutConstructor();
	$qb->setParameters(new ArrayCollection());

	return $qb;
}


/** @return array{ShipmentQuery, QueryBuilder} */
function applyFilter(string $filter, TestSecurityUser $securityUser): array
{
	$query = new ShipmentQuery();
	$query->setSecurityUser($securityUser);
	$query->init();

	$qb = createQueryBuilder();
	($query->filter[$filter])($qb);

	return [$query, $qb];
}


test('vice tenantu: bez plneho pristupu se data omezi na ucty uzivatele', function () {
	[$query, $qb] = applyFilter(BaseQuery::SECURITY_FILTER, new TestSecurityUser(identity: new TestIdentity()));

	Assert::same(['e.account'], $query->joins);
	Assert::same('account IN (:account) OR account.parent IN (:account)', (string) $qb->getDQLPart('where'));
	Assert::notNull($qb->getParameter('account'));
});


test('vice tenantu: s plnym pristupem se data neomezuji', function () {
	[$query, $qb] = applyFilter(BaseQuery::SECURITY_FILTER, new TestSecurityUser(['fullData'], identity: new TestIdentity()));

	Assert::same([], $query->joins);
	Assert::null($qb->getDQLPart('where'));
});


test('single-tenant: bezpecnostni filtr na ucet nesaha', function () {
	[$query, $qb] = applyFilter(BaseQuery::SECURITY_FILTER, new TestSecurityUser(identity: new TestIdentity(), isMultiTenant: false));

	Assert::same([], $query->joins);
	Assert::null($qb->getDQLPart('where'));
	Assert::count(0, $qb->getParameters());
});


test('nepřihlášený nevidí nic v žádném režimu', function () {
	foreach ([true, false] as $isMultiTenant) {
		[$query, $qb] = applyFilter(BaseQuery::SECURITY_FILTER, new TestSecurityUser(isLoggedIn: false, isMultiTenant: $isMultiTenant));

		Assert::same([], $query->joins);
		Assert::same('e.id IS NULL', (string) $qb->getDQLPart('where'));
	}
});


test('vice tenantu: uctovy filtr omezi data na vybrany ucet', function () {
	$identity = new TestIdentity()->setSelectedAccount(new TestAccount('Firma'));
	[$query, $qb] = applyFilter(BaseQuery::ACCOUNT_FILTER, new TestSecurityUser(identity: $identity));

	Assert::same(['e.account'], $query->joins);
	Assert::same('account = :account OR account.parent = :account', (string) $qb->getDQLPart('where'));
});


test('single-tenant: uctovy filtr ucet z doby pred prepnutim ignoruje', function () {
	$identity = new TestIdentity()->setSelectedAccount(new TestAccount('Firma'));
	[$query, $qb] = applyFilter(BaseQuery::ACCOUNT_FILTER, new TestSecurityUser(identity: $identity, isMultiTenant: false));

	Assert::same([], $query->joins);
	Assert::null($qb->getDQLPart('where'));
});
