<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

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

	public function addCustomerRoute(string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): void
	{
		$metadata = array_merge(
			[
				'selectedAccount' => [
					RouteAlias::FilterIn => function (string $selectedAccount) {
						// nette vse natvrdo pretypovava na string
						$selectedAccount = (int) $selectedAccount;

						if ($this->securityUser->isLoggedIn()) {
							try {
								if ($this->securityUser->getIdentity()->getSelectedAccount()?->getId() !== $selectedAccount) {
									$this->securityUser->getIdentity()->setSelectedAccount($this->accountQueryFactory->create()->disableAccountFilter()->byId($selectedAccount)->fetchOne());
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

		$this->addRoute(
			'<selectedAccount \d+>/' . $mask,
			$metadata,
			$oneWay
		);
	}
}