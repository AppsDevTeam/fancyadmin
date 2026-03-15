<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Acl;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\Model\Entities\Acl;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclRole;

trait AclPresenterTrait
{
	use PresenterTrait;
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;

	private string $aclEntityClass;
	private string $aclRoleEntityClass;
	private string $aclResourceEntityClass;

	public function startup(): void
	{
		parent::startup();

		$this->aclEntityClass = $this->_em->findEntityClassByInterface(Acl::class);
		$this->aclRoleEntityClass = $this->_em->findEntityClassByInterface(AclRole::class);
		$this->aclResourceEntityClass = $this->_em->findEntityClassByInterface(AclResource::class);
	}

	public function actionDefault(): void
	{
		$this->template->roles = $this->_aclRoleQueryFactory->create()
			->byIsAdmin(false)
			->fetchPairs();

		$this->template->resources = $this->_em->getRepository($this->aclResourceEntityClass)
			->createQueryBuilder('acl_resources')
			->select('acl_resources')
			->orderBy('acl_resources.title')
			->getQuery()
			->getResult();

		$this->template->rolesResources = $this->_em->getRepository($this->aclEntityClass)
			->createQueryBuilder('acl')
			->select('role.id AS roleId, resource.id AS resourceId')
			->innerJoin('acl.role', 'role')
			->innerJoin('acl.resource', 'resource')
			->andWhere('acl.isActive = :isActive')
			->setParameter('isActive', true)
			->getQuery()
			->getResult();

		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function handlePermission(int $roleId, int $resourceId, bool $bool): void
	{
		/** @var AclRole $aclRole */
		$aclRole = $this->_em->getRepository($this->aclRoleEntityClass)->find($roleId);
		/** @var AclResource $aclResource */
		$aclResource = $this->_em->getRepository($this->aclResourceEntityClass)->find($resourceId);

		if (!$aclRole || !$aclResource) {
			$this->error();
		}

		/** @var Acl|null $acl */
		$acl = $this->_em->getRepository($this->aclEntityClass)
			->createQueryBuilder('acl')
			->andWhere('acl.role = :role')
			->andWhere('acl.resource = :resource')
			->setParameter('role', $aclRole)
			->setParameter('resource', $aclResource)
			->getQuery()
			->getOneOrNullResult();

		if ($bool) {
			if ($acl) {
				$acl->setIsActive(true);
			} else {
				$acl = new $this->aclEntityClass();
				$acl->setRole($aclRole);
				$acl->setResource($aclResource);
				$this->_em->persist($acl);
			}
		} else {
			if (!$acl) {
				$this->error();
			}

			$acl->setIsActive(false);
		}

		$this->_em->flush();
		$this->redirect('default');
	}
}
