<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestProfile;
use Tester\Assert;

/**
 * ProfileTrait - clenstvi identity v uctu vcetne roli platnych jen tam.
 */

require __DIR__ . '/bootstrap.php';


test('profil sparuje identitu s uctem', function () {
	$identity = new TestIdentity();
	$account = new TestAccount('Firma');

	$profile = new TestProfile($identity, $account);

	Assert::same($identity, $profile->getIdentity());
	Assert::same($account, $profile->getAccount());
});


test('nastaveni identity prida profil i na druhou stranu', function () {
	$identity = new TestIdentity();
	$profile = new TestProfile(account: new TestAccount('Firma'));

	$profile->setIdentity($identity);

	Assert::same([$profile], $identity->getProfiles());
});


test('ucet jde vymenit', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));
	$jiny = new TestAccount('Jina firma');

	Assert::same($jiny, $profile->setAccount($jiny)->getAccount());
});


test('profil je ve vychozim stavu aktivni', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));

	Assert::true($profile->getIsActive());
	Assert::false($profile->setIsActive(false)->getIsActive());
});


test('novy profil nema role', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));

	Assert::same([], $profile->getRoles());
	Assert::false($profile->isAllowed(new StringResource('cokoliv')));
});


test('stejna role se neprida dvakrat', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));
	$role = new TestAclRole('editor');

	$profile->addRole($role)->addRole($role);

	Assert::same([$role], $profile->getRoles());
});


test('profil povoluje zdroje svych roli', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));
	$profile->addRole(new TestAclRole('editor')->allowResource(new TestAclResource('portalCustomer.orders')));

	Assert::true($profile->isAllowed(new StringResource('portalCustomer.orders')));
	Assert::false($profile->isAllowed(new StringResource('portalCustomer.invoices')));
});


test('admin role v profilu povoluje vse', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));
	$profile->addRole(new TestAclRole('admin', isAdmin: true));

	Assert::true($profile->isAllowed(new StringResource('cokoliv')));
});


test('vypnuta vazba role na zdroj opravneni nedava', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));
	$profile->addRole(new TestAclRole('editor')->allowResource(new TestAclResource('portalCustomer.orders'), isActive: false));

	Assert::false($profile->isAllowed(new StringResource('portalCustomer.orders')));
});


test('staci jedna role s opravnenim', function () {
	$profile = new TestProfile(new TestIdentity(), new TestAccount('Firma'));
	$profile
		->addRole(new TestAclRole('viewer'))
		->addRole(new TestAclRole('editor')->allowResource(new TestAclResource('portalCustomer.orders')));

	Assert::true($profile->isAllowed(new StringResource('portalCustomer.orders')));
});
