<?php

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\Administration;
use ADT\Routing\Route;
use Closure;
use ADT\Routing\RouteList;

class FancyAdminRouter
{
	public function __construct(
		protected Administration $administration
	) {}

	public function createAdminRouteModule(): RouteList {
		$adminModule = new FancyAdminRouteList($this->administration, 'Admin');

		$adminModule->addAdminRoute('sign/in', [
			'presenter' => 'Sign',
			'action' => 'in',
		]);

		$adminModule->addAdminRoute('sign/out', [
			'presenter' => 'Sign',
			'action' => 'out',
		]);

		if ($this->administration->isLostPasswordEnabled()) {
			$adminModule->addAdminRoute('sign/lost-password', [
				'presenter' => 'Sign',
				'action' => 'lostPassword',
			]);
		}

		return $adminModule;
	}
}