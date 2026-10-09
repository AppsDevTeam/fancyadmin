<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAcl;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use Tester\Assert;

/**
 * AclRoleTrait, AclResourceTrait a AclTrait - role, zdroje a jejich propojeni.
 */

require __DIR__ . '/bootstrap.php';


test('zdroj ma nazev a titulek', function () {
	$resource = new TestAclResource('portalBackoffice.orders', 'Objednavky');

	Assert::same('portalBackoffice.orders', $resource->getName());
	Assert::same('Objednavky', $resource->getTitle());

	$resource->setName('jiny')->setTitle('Jiny');
	Assert::same('jiny', $resource->getName());
	Assert::same('Jiny', $resource->getTitle());
});


test('role se navenek predstavuje svym nazvem', function () {
	$role = new TestAclRole('editor');

	Assert::same('editor', $role->getName());
	// Nette\Security\Role pracuje s retezcovym id, u nas je to nazev role.
	Assert::same('editor', $role->getRoleId());

	$role->setName('jiny');
	Assert::same('jiny', $role->getRoleId());
});


test('role nese typ, kontext a priznaky', function () {
	$role = new TestAclRole('editor');

	Assert::same(AclRoleTypeEnum::IDENTITY, $role->getType());
	Assert::null($role->getContext());
	Assert::false($role->getIsAdmin());
	Assert::false($role->getNeedsSso());
	Assert::false($role->getNeeds2fa());

	$role->setType(AclRoleTypeEnum::PROFILE)->setContext('admin')->setIsAdmin(true)->setNeedsSso(true)->setNeeds2fa(true);

	Assert::same(AclRoleTypeEnum::PROFILE, $role->getType());
	Assert::same('admin', $role->getContext());
	Assert::true($role->getIsAdmin());
	Assert::true($role->getNeedsSso());
	Assert::true($role->getNeeds2fa());
});


test('nova role zadne zdroje nema', function () {
	Assert::same([], new TestAclRole('editor')->getResources());
});


test('do zdroju se pocitaji jen aktivni vazby', function () {
	$orders = new TestAclResource('portalBackoffice.orders');
	$identities = new TestAclResource('portalBackoffice.identities');

	$role = new TestAclRole('editor')
		->allowResource($orders)
		->allowResource($identities, isActive: false);

	Assert::same([$orders], array_values($role->getResources()));
});


test('role povoluje jen sve aktivni zdroje', function () {
	$role = new TestAclRole('editor')->allowResource(new TestAclResource('portalBackoffice.orders'));

	Assert::true($role->isAllowed(new StringResource('portalBackoffice.orders')));
	Assert::false($role->isAllowed(new StringResource('portalBackoffice.identities')));
});


test('nazev zdroje se porovnava presne', function () {
	$role = new TestAclRole('editor')->allowResource(new TestAclResource('portalBackoffice.orders'));

	Assert::false($role->isAllowed(new StringResource('portalBackoffice.order')));
	Assert::false($role->isAllowed(new StringResource('portalBackoffice.orders.detail')));
	Assert::false($role->isAllowed(new StringResource('PortalBackoffice.Orders')));
});


test('admin projde bez ohledu na zdroje', function () {
	$role = new TestAclRole('admin', isAdmin: true);

	Assert::true($role->isAllowed(new StringResource('cokoliv')));
	Assert::same([], $role->getResources());
});


test('role prijme i zdroj z enumu', function () {
	$role = new TestAclRole('editor')->allowResource(new TestAclResource(AclResourceNameEnum::FULL_DATA->value));

	Assert::true($role->isAllowed(AclResourceNameEnum::FULL_DATA));
	Assert::false($role->isAllowed(AclResourceNameEnum::BACKOFFICE_DASHBOARD));
});


test('vazba role na zdroj drzi obe strany', function () {
	$role = new TestAclRole('editor');
	$resource = new TestAclResource('portalBackoffice.orders');
	$acl = new TestAcl($role, $resource);

	Assert::same($role, $acl->getRole());
	Assert::same($resource, $acl->getResource());
	Assert::true($acl->getIsActive());

	$jinaRole = new TestAclRole('viewer');
	$jinyZdroj = new TestAclResource('portalBackoffice.identities');
	$acl->setRole($jinaRole)->setResource($jinyZdroj)->setIsActive(false);

	Assert::same($jinaRole, $acl->getRole());
	Assert::same($jinyZdroj, $acl->getResource());
	Assert::false($acl->getIsActive());
});
