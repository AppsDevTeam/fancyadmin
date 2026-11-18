<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControl;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControlFactory;

/** Presenter s dashboardem (docs/widgets.md): komponenta `dashboard`, widgety skládá `configureDashboard()`. */
trait Dashboard
{
	abstract protected function configureDashboard(WidgetGridControl $dashboard): void;

	public function createComponentDashboard(WidgetGridControlFactory $factory): WidgetGridControl
	{
		$dashboard = $factory->create();
		$this->configureDashboard($dashboard);
		return $dashboard;
	}
}
