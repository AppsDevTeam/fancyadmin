<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;

class FancyAdminRouter
{
	public function __construct(
		protected FancyAdmin $administration,
		protected SecurityUser $securityUser,
		protected EntityManagerInterface $em,
		protected AccountQueryFactory $accountQueryFactory,
	) {}

	public function createAdminRouteModule(): FancyAdminRouteList
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

		$adminModule->addRoute('sign/token', [
			'presenter' => 'Sign',
			'action' => 'token',
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

		return $adminModule;
	}
}