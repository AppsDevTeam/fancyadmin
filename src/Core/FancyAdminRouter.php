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

	public function getAdminHost(): string
	{
		$adminHostPath = $this->administration->getAdminHostPath();

		$host = explode('/', $adminHostPath);
		if ($host[0] !== '') {
			$adminHostPath = 'https://' . $adminHostPath;
		}

		return $adminHostPath;
	}

	public function createAdminRouteModule(): RouteList {
		$adminModule = new RouteList('Admin');

		$this->createAdminRoute($adminModule, 'sign/in', [
			'presenter' => 'Sign',
			'action' => 'in',
		]);

		$this->createAdminRoute($adminModule, 'sign/out', [
			'presenter' => 'Sign',
			'action' => 'out',
		]);

		if ($this->administration->isLostPasswordEnabled()) {
			$this->createAdminRoute($adminModule, 'sign/lost-password', [
				'presenter' => 'Sign',
				'action' => 'lostPassword',
			]);
		}

		return $adminModule;
	}

	private function createAdminRoute(RouteList $adminModule, string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): void
	{
		$adminModule->addRoute(
			$this->getAdminHost() . '/' . $mask,
			$metadata,
			$oneWay
		);
	}
}