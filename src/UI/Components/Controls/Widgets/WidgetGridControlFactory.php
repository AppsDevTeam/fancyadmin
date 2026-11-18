<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

interface WidgetGridControlFactory
{
	public function create(): WidgetGridControl;
}
