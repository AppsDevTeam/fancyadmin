<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Nette\Application\BadRequestException;
use Nette\Application\Routers\RouteList;
use Nette\Routing\Route as RouteAlias;

class FancyAdminRouteList extends RouteList
{
	public function __construct(
		string $module,
		protected FancyAdmin $administration,
		protected SecurityUser $securityUser,
		protected EntityManagerInterface $em,
		protected AccountQueryFactory $accountQueryFactory,
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
									/** @var Account $account */
									$account = $this->accountQueryFactory->create()->byId($selectedAccount)->fetchOne();
									$this->securityUser->getIdentity()->setSelectedAccount($account);
									$this->em->flush();
								}
							} catch (NoResultException) {
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