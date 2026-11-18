<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControl;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControlFactory;

/** Presenter s dashboardem (docs/dashboard.md): komponenta `dashboard`, widgety skládá `configureDashboard()`. */
trait Dashboard
{
	abstract protected function configureDashboard(DashboardControl $dashboard): void;

	public function createComponentDashboard(DashboardControlFactory $factory): DashboardControl
	{
		$dashboard = $factory->create();
		$this->configureDashboard($dashboard);
		return $dashboard;
	}
}
