<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

interface DashboardControlFactory
{
	public function create(): DashboardControl;
}
