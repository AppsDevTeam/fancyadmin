<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\Routing\RouteList;
use Doctrine\ORM\EntityManagerInterface;

class FancyAdminRouter
{
	public function __construct(
		protected FancyAdmin             $administration,
		protected SecurityUser           $securityUser,
		protected EntityManagerInterface $em,
	) {}

	public function createAdminRouteModule(): FancyAdminRouteList {
		$adminModule = new FancyAdminRouteList('Portal', $this->administration, $this->securityUser, $this->em);

		$adminModule->addRoute('sign/in', [
			'presenter' => 'Sign',
			'action' => 'in',
		]);

		$adminModule->addRoute('sign/out', [
			'presenter' => 'Sign',
			'action' => 'out',
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