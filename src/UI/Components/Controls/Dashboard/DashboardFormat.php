<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

final class DashboardFormat
{
	/** Číslo po česku (mezera mezi tisíci, desetinná čárka), text beze změny. */
	public static function value(int|float|string|null $value): string
	{
		if ($value === null) {
			return '';
		}
		if (is_string($value)) {
			return $value;
		}
		$decimals = is_float($value) && floor($value) !== $value ? 2 : 0;

		return number_format($value, $decimals, ',', "\u{a0}");
	}

	/** Ikona Font Awesome: holé jméno dostane `fa-solid`, plné třídy (`fa-regular fa-box`) zůstanou. */
	public static function icon(string $icon): string
	{
		return str_starts_with($icon, 'fa-') ? $icon : 'fa-solid fa-' . $icon;
	}
}
