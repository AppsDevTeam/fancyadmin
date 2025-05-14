<?php

namespace ADT\FancyAdmin\Core;

use Closure;
use ADT\Routing\RouteList;

trait FancyAdminRouter
{
	abstract public static function getAdminHost(): string;

	public static function createAdminRouteModule(): RouteList {
		$adminModule = new RouteList('Admin');

		self::createAdminRoute($adminModule, 'sign/<action>[/<id>]', [
			'presenter' => 'Sign',
			'action' => 'default',
		]);

		return $adminModule;
	}

	private static function createAdminRoute(RouteList $adminModule, string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): void
	{
		$adminModule->addRoute(
			'https://' . self::getAdminHost() . '/' . $mask,
			$metadata,
			$oneWay
		);
	}
}