<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Acl;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclRole;

class Permission extends \Nette\Security\Permission
{
	public function __construct(protected EntityManager $em)
	{
		$this->setResources();
		$this->setRoles();
		$this->setAccess();
	}

	protected function setResources(): void
	{
		/** @var AclResource[] $resources */
		$resources = $this->em->getRepository($this->em->findEntityClassByInterface(AclResource::class))->findAll();

		foreach ($resources as $resource) {
			$this->addResource($resource->getName());
		}
	}

	protected function setRoles(): void
	{
		/** @var AclRole[] $roles */
		$roles = $this->em->getRepository($this->em->findEntityClassByInterface(AclRole::class))->findAll();

		foreach ($roles as $role) {
			$this->addRole($role->getRoleId());
		}
	}

	public function setAccess(): void
	{
		$allows = $this->em->getRepository($this->em->findEntityClassByInterface(Acl::class))
			->createQueryBuilder('acl')
			->select('role.name AS roleName, resource.name AS resourceName')
			->innerJoin('acl.role', 'role')
			->innerJoin('acl.resource', 'resource')
			->andWhere('acl.isActive = :isActive')
			->setParameter('isActive', true)
			->getQuery()
			->getResult();

		foreach ($allows as $allow) {
			$this->allow((string) $allow['roleName'], $allow['resourceName']);
		}
	}
}
