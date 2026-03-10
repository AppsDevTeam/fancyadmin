<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Permissions;

use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;

trait PermissionsPresenterTrait
{
	use PresenterTrait;
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;

	private string $aclRoleEntityClass;
	private string $aclResourceEntityClass;

	public function startup(): void
	{
		parent::startup();

		$this->aclRoleEntityClass = $this->_em->findEntityClassByInterface(AclRole::class);
		$this->aclResourceEntityClass = $this->_em->findEntityClassByInterface(AclResource::class);
	}

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ROLES_AND_PERMISSIONS)]
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

		$this->template->rolesResources = $this->_em->getRepository($this->aclRoleEntityClass)
			->createQueryBuilder('acl_roles')
			->select('acl_roles.id roleId, acl_resources.id resourceId')
			->innerJoin('acl_roles.resources', 'acl_resources')
			->getQuery()
			->getResult();

		$this->template->setFile(__DIR__ . '/default.latte');
	}


	/**
	 * Pridaj roli nove právo alebo jej odober existujúce právo
	 *
	 * @param int $roleId
	 * @param int $resourceId
	 * @param bool $bool
	 * @throws \Exception
	 */
	public function handlePermission(int $roleId, int $resourceId, bool $bool)
	{
		$rolesResource = $this->_em->getRepository($this->aclRoleEntityClass)
			->createQueryBuilder('acl_roles')
			->select('acl_roles.id roleId, acl_resources.id resourceId')
			->innerJoin('acl_roles.resources', 'acl_resources')
			->andWhere('acl_roles.id = :roleId')
			->setParameter('roleId', $roleId)
			->andWhere('acl_resources.id = :resourceId')
			->setParameter('resourceId', $resourceId)
			->getQuery()
			->getResult();

		/** @var AclRole $aclRole */
		$aclRole = $this->_em->getRepository($this->aclRoleEntityClass)
			->createQueryBuilder('acl_role')
			->andWhere('acl_role.id = :roleId')
			->setParameter('roleId', $roleId)
			->getQuery()
			->getOneOrNullResult();

		/** @var AclResource $aclResource */
		$aclResource = $this->_em->getRepository($this->aclResourceEntityClass)
			->createQueryBuilder('acl_resource')
			->andWhere('acl_resource.id = :resourceId')
			->setParameter('resourceId', $resourceId)
			->getQuery()
			->getOneOrNullResult();


		// Pridavame nové právo
		if ($bool) {
			// Prava uz su priradene
			if ($rolesResource) {
				$this->error();
			}
			$aclRole->addResource($aclResource);
		} else { // Odoberáme existujúce právo
			// Chceme odobrat pravo, ktore uz existuje
			if (!$rolesResource) {
				$this->error();
			}
			$aclRole->removeResource($aclResource);
		}

		$this->_em->flush();
		$this->redirect('default');
	}
}
