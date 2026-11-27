<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/** Kam si mřížka pamatuje zvolené období (WidgetGridControl::setPeriodRemembered) - výchozí relace uživatele. */
interface WidgetPeriodStorage
{
	public function get(string $key): ?string;

	public function set(string $key, string $period): void;
}
