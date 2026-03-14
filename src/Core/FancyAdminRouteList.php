<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\Routing\Route;
use ADT\Routing\RouteList;
use Doctrine\ORM\EntityManagerInterface;

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

	public function createRoute(string $mask, $metadata = [], int $flags = 0): Route
	{
		return parent::createRoute($this->getAdminHost() . '/' . $mask, $metadata, $flags);
	}
}