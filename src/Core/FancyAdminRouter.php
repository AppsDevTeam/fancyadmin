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
	private ?\ADT\Routing\RouteList $routeList = null;

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

			$this->customerRouteList->addRoute('<presenter>/[/<id>][/<action>]', [
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

			$this->backofficeRouteList->addRoute('<presenter>[/<id \d+>][/<action>]', [
				'presenter' => 'Home',
				'action' => 'default',
			]);
		}

		return $this->backofficeRouteList;
	}

	public function getRouteList(): \ADT\Routing\RouteList
	{
		if ($this->routeList === null) {
			$this->routeList = new \ADT\Routing\RouteList();

			$portal = new FancyAdminRouteList(
				'Portal',
				$this->administration,
				$this->securityUser,
				$this->em,
				$this->accountQueryFactory
			);

			$portal->addRoute('sign/in', [
				'presenter' => 'Sign',
				'action' => 'in',
			]);

			$portal->addRoute('sign/out', [
				'presenter' => 'Sign',
				'action' => 'out',
			]);

			$portal->addRoute('sign/new-password', [
				'presenter' => 'Sign',
				'action' => 'newPassword',
			]);

			if ($this->administration->isLostPasswordEnabled()) {
				$portal->addRoute('sign/lost-password', [
					'presenter' => 'Sign',
					'action' => 'lostPassword',
				]);
			}

			$this->routeList[] = $portal;
			$this->routeList[] = $this->getCustomerRouteList();
			$this->routeList[] = $this->getBackofficeRouteList();
		}

		return $this->routeList;
	}

	public function createRouteList(): \ADT\Routing\RouteList
	{
		return $this->getRouteList();
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