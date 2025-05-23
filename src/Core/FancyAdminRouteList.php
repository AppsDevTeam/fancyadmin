<?php

namespace ADT\FancyAdmin\Core;

use ADT\FancyAdmin\Model\Administration;
use ADT\Routing\TranslatorInterface;
use Closure;
use ADT\Routing\RouteList;

class FancyAdminRouteList extends RouteList
{
	public Administration $administration;

	public function __construct(Administration $administration, ?string $module = null, ?TranslatorInterface $translator = null)
	{
		$this->administration = $administration;
		parent::__construct($module, $translator);
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

	public function addAdminRoute(string $mask, Closure|array|string $metadata = [], int|bool $oneWay = 0): self
	{
		$this->addRoute(
			$this->getAdminHost() . '/' . $mask,
			$metadata,
			$oneWay
		);
		return $this;
	}
}