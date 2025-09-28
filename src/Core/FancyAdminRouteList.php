<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\Routing\TranslatorInterface;
use Closure;
use ADT\Routing\RouteList;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Nette\Application\BadRequestException;
use Nette\Routing\Route as RouteAlias;

class FancyAdminRouteList extends \Nette\Application\Routers\RouteList
{
	public function __construct(
		string                           $module,
		protected FancyAdmin             $administration,
		protected SecurityUser           $securityUser,
		protected EntityManagerInterface $em,
	) {
		parent::__construct($module);
	}

	public function getAdminHost(): string
	{
		$adminHostPath = $this->administration->getAdminHostPath();

		$host = explode('/', $adminHostPath);
		if ($host[0] !== '') {
			$adminHostPath = 'https://' . $adminHostPath;
		}

		return $adminHostPath;
	}

	public function addRoute(string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): static
	{
		parent::addRoute(
			$this->getAdminHost() . '/' . $mask,
			$metadata,
			$oneWay
		);
		return $this;
	}

	public function addBackofficeRoute(string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): static
	{
		parent::addRoute(
			$this->getAdminHost() . '/' . $mask,
			$metadata,
			$oneWay
		);
		return $this;
	}

	public function addCustomerRoute(string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): static
	{
		$metadata = array_merge(
			[
				'selectedAccount' => [
					RouteAlias::FilterIn => function (string $selectedAccount) {
						if ($this->securityUser->isLoggedIn()) {
							try {
								if (!$this->securityUser->getIdentity()->getSelectedAccount()) {
									// TODO disableCompanyFilter
									$this->securityUser->getIdentity()->setSelectedAccount($this->administration->createAccountQuery()->byId($selectedAccount)->fetchOne());
									$this->em->flush();
								}
							} catch (NoResultException $e) {
								throw new BadRequestException();
							}
						}
						return $selectedAccount;
					},
				]
			],
			$metadata
		);

		$this->addRoute($mask, $metadata, $oneWay);

		return $this;
	}
}