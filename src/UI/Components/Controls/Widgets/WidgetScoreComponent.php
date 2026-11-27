<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/** Složka hodnocení (WidgetScore): název, hodnota z `max` s pruhem v barvě tónu; `value` null = „–“ a prázdný pruh. */
final class WidgetScoreComponent
{
	public function __construct(
		public readonly string $label,
		public readonly int|float|null $value = null,
		public readonly WidgetTone $tone = WidgetTone::Neutral,
		public readonly int|float $max = 100,
	) {
	}

	/** Podíl hodnoty (0–1) pro pruh. */
	public function getRatio(): float
	{
		return $this->value === null || $this->max <= 0 ? 0.0 : max(0.0, min(1.0, $this->value / $this->max));
	}
}
