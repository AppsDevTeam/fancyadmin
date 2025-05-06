<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Doctrine\EntityManager;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Enums\AclRoleEnum;

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
		$resources = $this->em->getRepository(AclResource::class)->findAll();

		foreach ($resources as $resource) {
			$this->addResource($resource->getName());
		}
	}

	protected function setRoles(): void
	{
		/** @var AclRole[] $roles */
		$roles = $this->em->getRepository(AclRole::class)->findAll();

		foreach ($roles as $role) {
			$this->addRole($role->getRoleId());
		}
	}

	public function setAccess(): void
	{
		$allows = $this->em->getRepository(AclRole::class)
			->createQueryBuilder('role')
			->select('role.name AS roleName, resource.name AS resourceName')
			->innerJoin('role.resources', 'resource')
			->getQuery()
			->getResult();

		foreach ($allows as $allow) {
			$this->allow((string) $allow['roleName'], $allow['resourceName']);
		}

		// Adminovi povol vše
		$this->allow((string) AclRoleEnum::ROLE_ADMIN);
	}
}
