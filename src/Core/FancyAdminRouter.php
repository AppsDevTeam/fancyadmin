<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\BadRequestException;
use Nette\Http\IResponse;
use Nette\Routing\Route as RouteAlias;

class FancyAdminRouter
{
	private ?FancyAdminCustomerRouteList $customerRouteList = null;
	private ?FancyAdminRouteList $backofficeRouteList = null;
	private ?FancyAdminRouteList $portalRouteList = null;

	public function __construct(
		protected FancyAdmin $administration,
		protected SecurityUser $securityUser,
		protected EntityManagerInterface $em,
		protected AccountQueryFactory $accountQueryFactory,
	) {}

	public function getCustomerRouteList(): FancyAdminCustomerRouteList
	{
		if ($this->customerRouteList === null) {
			$this->customerRouteList = new FancyAdminCustomerRouteList(
				'PortalCustomer',
				$this->administration,
				$this->securityUser,
				$this->em,
				$this->accountQueryFactory
			);

			$this->customerRouteList->addRoute('<presenter>/<id \d+>', [
				'presenter' => 'Home',
				'action' => 'detail',
			]);

			$this->customerRouteList->addRoute('<presenter>[/<id \d+>][/<action>]', [
				'presenter' => 'Home',
				'action' => 'default',
			]);
		}

		return $this->customerRouteList;
	}

	public function getBackofficeRouteList(): FancyAdminRouteList
	{
		if ($this->backofficeRouteList === null) {
			$this->backofficeRouteList = new FancyAdminRouteList(
				'PortalBackoffice',
				$this->administration,
				$this->securityUser,
				$this->em,
				$this->accountQueryFactory
			);

			$this->backofficeRouteList->addRoute('<presenter>/<id \d+>', [
				'presenter' => 'Home',
				'action' => 'detail',
			]);

			$this->backofficeRouteList->addRoute('<presenter>[/<id \d+>][/<action>]', [
				'presenter' => 'Home',
				'action' => 'default',
			]);
		}

		return $this->backofficeRouteList;
	}

	public function getPortalRouteList(): FancyAdminRouteList
	{
		if ($this->portalRouteList === null) {
			$this->portalRouteList = new FancyAdminRouteList(
				'Portal',
				$this->administration,
				$this->securityUser,
				$this->em,
				$this->accountQueryFactory
			);

			$this->portalRouteList->addRoute('sign/in', [
				'presenter' => 'Sign',
				'action' => 'in',
			]);

			$this->portalRouteList->addRoute('sign/out', [
				'presenter' => 'Sign',
				'action' => 'out',
			]);

			$this->portalRouteList->addRoute('sign/new-password', [
				'presenter' => 'Sign',
				'action' => 'newPassword',
			]);

			$this->portalRouteList->addRoute('sign/password-set', [
				'presenter' => 'Sign',
				'action' => 'passwordSet',
			]);

			if ($this->administration->isLostPasswordEnabled()) {
				$this->portalRouteList->addRoute('sign/lost-password', [
					'presenter' => 'Sign',
					'action' => 'lostPassword',
				]);
			}
		}

		return $this->portalRouteList;
	}

	public function createFilterByQueryObject(BaseQuery $query): array
	{
		return [
			RouteAlias::FilterIn => function ($entity) use ($query) {
				if ($entity = $query->byId($entity)->fetchOneOrNull()) {
					return $entity;
				}

				throw new BadRequestException(httpCode: IResponse::S404_NotFound);
			},
			RouteAlias::FilterOut => fn(Entity $entity) => $entity->getId()
		];
	}
}
