<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestAuthenticator;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUserTraitHost;
use Nette\Security\AuthenticationException;
use Nette\Security\Permission;
use Tester\Assert;

/**
 * SecurityUserTrait a AuthenticatorTrait.
 *
 * Opravneni se resi pres ACL role: role s priznakem admina projde vzdy, ostatni se ptaji
 * autorizatoru. Prihlaseni bez kontextu balicek nepusti - kontext oddeluje uzivatele
 * ruznych casti platformy.
 */

require __DIR__ . '/bootstrap.php';


const ALL_RESOURCES = ['portalBackoffice.orders', 'portalBackoffice.identities', 'fullData', 'portalBackoffice.dashboard'];

function createAuthorizator(array $allowed = []): Permission
{
	$permission = new Permission();

	foreach (ALL_RESOURCES as $resource) {
		$permission->addResource($resource);
	}

	foreach ($allowed as $role => $resources) {
		$permission->addRole($role);
		foreach ($resources as $resource) {
			$permission->allow($role, $resource);
		}
	}

	return $permission;
}


test('role admina projde bez ptani autorizatoru', function () {
	// Autorizator zdroj vubec nezna - u admina se k nemu nedojde.
	$identity = new TestIdentity()->addRole(new TestAclRole('admin', isAdmin: true));
	$user = new TestSecurityUserTraitHost($identity, createAuthorizator());

	Assert::true($user->isAdmin());
	Assert::true($user->isAllowed(new StringResource('neznamy.zdroj')));
});


test('bezna role se pta autorizatoru', function () {
	$identity = new TestIdentity()->addRole(new TestAclRole('editor'));
	$user = new TestSecurityUserTraitHost($identity, createAuthorizator(['editor' => ['portalBackoffice.orders']]));

	Assert::false($user->isAdmin());
	Assert::true($user->isAllowed('portalBackoffice.orders'));
	Assert::false($user->isAllowed('portalBackoffice.identities'));
});


test('staci jedna role s opravnenim', function () {
	$identity = new TestIdentity()
		->addRole(new TestAclRole('viewer'))
		->addRole(new TestAclRole('editor'));
	$user = new TestSecurityUserTraitHost($identity, createAuthorizator([
		'viewer' => [],
		'editor' => ['portalBackoffice.orders'],
	]));

	Assert::true($user->isAllowed('portalBackoffice.orders'));
});


test('uzivatel bez roli nema zadne opravneni', function () {
	$user = new TestSecurityUserTraitHost(new TestIdentity(), createAuthorizator());

	Assert::false($user->isAdmin());
	Assert::false($user->isAllowed('portalBackoffice.orders'));
});


test('zdroje pro plna data a backoffice se nastavuji zvenci', function () {
	$identity = new TestIdentity()->addRole(new TestAclRole('editor'));
	$user = new TestSecurityUserTraitHost($identity, createAuthorizator([
		'editor' => ['fullData'],
	]));

	$user->setFullDataAclResource(AclResourceNameEnum::FULL_DATA);
	$user->setBackofficeAclResource(AclResourceNameEnum::BACKOFFICE_DASHBOARD);

	Assert::true($user->isAllowedFullDataAclResource());
	Assert::false($user->isAllowedBackoffice());
});


test('prihlaseni bez kontextu neprojde', function () {
	// Kontext oddeluje uzivatele ruznych casti platformy - bez nej by se prihlasil
	// zakaznik do backoffice a naopak.
	$user = new TestSecurityUserTraitHost();

	Assert::exception(fn() => $user->login('jan@example.com', 'heslo'), Exception::class, 'Context is required.');
	Assert::exception(fn() => $user->login('jan@example.com', 'heslo', ''), Exception::class, 'Context is required.');
	Assert::same([], $user->loginCalls);
});


test('prihlaseni s kontextem se preda dal', function () {
	$user = new TestSecurityUserTraitHost();

	$user->login('jan@example.com', 'heslo', 'admin', ['device' => 'mobil']);

	Assert::same([['jan@example.com', 'heslo', 'admin', ['device' => 'mobil']]], $user->loginCalls);
});


test('bez opravneni do zakaznicke ani backoffice casti se identita neprihlasi', function () {
	$authenticator = new TestAuthenticator();
	$authenticator->setFancyAdmin(FancyAdminFactory::create());

	$identity = new TestIdentity()->addRole(new TestAclRole('nikam'));

	Assert::exception(
		fn() => $authenticator->callValidateIdentity($identity),
		AuthenticationException::class,
		'Nemáte oprávnění pro přihlášení',
	);
});


test('staci opravneni do jedne z casti', function () {
	$authenticator = new TestAuthenticator();
	$authenticator->setFancyAdmin(FancyAdminFactory::create());

	$zakaznik = new TestIdentity()->addRole(
		new TestAclRole('zakaznik')->allowResource(new TestAclResource(AclResourceNameEnum::CUSTOMER_DASHBOARD->value)),
	);
	$backoffice = new TestIdentity()->addRole(
		new TestAclRole('operator')->allowResource(new TestAclResource(AclResourceNameEnum::BACKOFFICE_DASHBOARD->value)),
	);

	Assert::noError(fn() => $authenticator->callValidateIdentity($zakaznik));
	Assert::noError(fn() => $authenticator->callValidateIdentity($backoffice));
});


test('admin projde vzdy', function () {
	$authenticator = new TestAuthenticator();
	$authenticator->setFancyAdmin(FancyAdminFactory::create());

	Assert::noError(fn() => $authenticator->callValidateIdentity(new TestIdentity()->addRole(new TestAclRole('admin', isAdmin: true))));
});
