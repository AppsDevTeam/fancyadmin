<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\Routing\RouteList;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\BadRequestException;
use Nette\Http\IResponse;
use Nette\Routing\Route as RouteAlias;

class FancyAdminRouter
{
	private ?FancyAdminCustomerRouteList $customerRouteList = null;
	private ?FancyAdminRouteList $backofficeRouteList = null;
	private ?FancyAdminRouteList $portalRouteList = null;
	private ?RouteList $routeList = null;

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

	/**
	 * Vrátí route list pro Keycloak auth/logout.
	 * Tyto routy musí být zaregistrovány PŘED FancyAdmin catch-all routami.
	 * Vrací null pokud Keycloak není zapnutý.
	 */
	public function getKeycloakRouteList(): ?RouteList
	{
		if (!$this->administration->isKeycloakEnabled()) {
			return null;
		}

		$adminHost = $this->getBackofficeRouteList()->getAdminHost();
		$keycloakRoutes = new RouteList('Portal');
		$keycloakRoutes->addRoute($adminHost . '/keycloak-auth/<action>', [
			'presenter' => 'KeycloakAuth',
		]);
		$keycloakRoutes->addRoute($adminHost . '/keycloak-log/<action>', [
			'presenter' => 'KeycloakLog',
		]);

		return $keycloakRoutes;
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

			if ($this->administration->isKeycloakEnabled()) {
				$this->portalRouteList->addRoute('keycloak-auth/<action>', [
					'presenter' => 'KeycloakAuth',
				]);
				$this->portalRouteList->addRoute('keycloak-log/<action>', [
					'presenter' => 'KeycloakLog',
				]);
			}
		}

		return $this->portalRouteList;
	}

	/**
	 * Vrátí kombinovaný route list (portal + customer + backoffice).
	 */
	public function getRouteList(): RouteList
	{
		if ($this->routeList === null) {
			$this->routeList = new RouteList();
			$this->routeList->add($this->getPortalRouteList());
			$this->routeList->add($this->getCustomerRouteList());
			$this->routeList->add($this->getBackofficeRouteList());
		}

		return $this->routeList;
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
