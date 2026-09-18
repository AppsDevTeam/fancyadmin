<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Security\Permission;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestRepository;
use Tester\Assert;

/**
 * Permission - Nette autorizator naplneny z databaze.
 *
 * Testuje se nacteni zdroju a roli; samotne vazby (setAccess) stavi Doctrine QueryBuilder
 * nad skutecnou databazi, takze se v testu nahrazuji.
 */

require __DIR__ . '/bootstrap.php';


/** Vazby role na zdroj se stavi dotazem do databaze - tady se dosazuji rovnou. */
final class TestPermission extends Permission
{
	/** @var list<array{string, string}> */
	public static array $access = [];

	public function setAccess(): void
	{
		foreach (self::$access as [$role, $resource]) {
			$this->allow($role, $resource);
		}
	}
}

function createPermission(array $resources, array $roles, array $access = []): TestPermission
{
	$em = new TestEntityManager([TestAclResource::class, TestAclRole::class]);

	/** @var TestRepository $resourceRepository */
	$resourceRepository = $em->getRepository($em->findEntityClassByInterface(AclResource::class));
	$resourceRepository->entities = $resources;

	/** @var TestRepository $roleRepository */
	$roleRepository = $em->getRepository($em->findEntityClassByInterface(AclRole::class));
	$roleRepository->entities = $roles;

	TestPermission::$access = $access;

	return new TestPermission($em);
}


test('zdroje a role se nactou z databaze', function () {
	$permission = createPermission(
		[new TestAclResource('portalBackoffice.orders'), new TestAclResource('portalBackoffice.identities')],
		[new TestAclRole('editor'), new TestAclRole('viewer')],
	);

	Assert::true($permission->hasResource('portalBackoffice.orders'));
	Assert::true($permission->hasResource('portalBackoffice.identities'));
	Assert::false($permission->hasResource('neexistuje'));

	Assert::same(['editor', 'viewer'], $permission->getRoles());
});


test('role se do autorizatoru uklada pod svym nazvem', function () {
	$permission = createPermission([], [new TestAclRole('spravce obsahu')]);

	Assert::true($permission->hasRole('spravce obsahu'));
});


test('prazdna databaze da prazdny autorizator', function () {
	$permission = createPermission([], []);

	Assert::same([], $permission->getRoles());
	Assert::same([], $permission->getResources());
});


test('vazby z databaze povoluji pristup', function () {
	$permission = createPermission(
		[new TestAclResource('portalBackoffice.orders'), new TestAclResource('portalBackoffice.identities')],
		[new TestAclRole('editor')],
		[['editor', 'portalBackoffice.orders']],
	);

	Assert::true($permission->isAllowed('editor', 'portalBackoffice.orders'));
	Assert::false($permission->isAllowed('editor', 'portalBackoffice.identities'));
});
