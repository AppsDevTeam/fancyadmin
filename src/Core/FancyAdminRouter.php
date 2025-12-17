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
	public function __construct(
		protected FancyAdmin $administration,
		protected SecurityUser $securityUser,
		protected EntityManagerInterface $em,
		protected AccountQueryFactory $accountQueryFactory,
	) {}

	public function createRouteModule(): FancyAdminRouteList
	{
		$adminModule = new FancyAdminRouteList(
			'Portal',
			$this->administration,
			$this->securityUser,
			$this->em,
			$this->accountQueryFactory
		);

		$adminModule->addRoute('sign/in', [
			'presenter' => 'Sign',
			'action' => 'in',
		]);

		$adminModule->addRoute('sign/out', [
			'presenter' => 'Sign',
			'action' => 'out',
		]);

		$adminModule->addRoute('sign/new-password', [
			'presenter' => 'Sign',
			'action' => 'newPassword',
		]);

		if ($this->administration->isLostPasswordEnabled()) {
			$adminModule->addRoute('sign/lost-password', [
				'presenter' => 'Sign',
				'action' => 'lostPassword',
			]);
		}

		$adminModule->addRoute('<presenter>[/<id>]', [
			'presenter' => 'Backoffice:Home',
			'action' => 'default',
		]);

		return $adminModule;
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