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
use Nette\Routing\RouteList;

class FancyAdminRouter
{
	public function __construct(
		protected FancyAdmin $administration,
		protected SecurityUser $securityUser,
		protected EntityManagerInterface $em,
		protected AccountQueryFactory $accountQueryFactory,
	) {}

	public function createRouteList()
	{
		$routeList = new \ADT\Routing\RouteList();

		$routeList[] = $portal = new FancyAdminRouteList(
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

		$routeList[] = $portal = new FancyAdminRouteList(
			'PortalCustomer',
			$this->administration,
			$this->securityUser,
			$this->em,
			$this->accountQueryFactory
		);

		$portal->addCustomerRoute('<presenter>/[/<id>][/<action>]', [
			'presenter' => 'Home',
			'action' => 'default',
		]);

		$routeList[] = $portal = new FancyAdminRouteList(
			'PortalBackoffice',
			$this->administration,
			$this->securityUser,
			$this->em,
			$this->accountQueryFactory
		);

		$portal->addRoute('<presenter>/[/<id>][/<action>]', [
			'presenter' => 'Home',
			'action' => 'default',
		]);

		return $routeList;
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