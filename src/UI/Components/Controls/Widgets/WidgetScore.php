<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Obsah hodnocení (ScoreWidget): skóre `value` z `max` v kruhu v barvě tónu, pod popiskem změna `change`
 * („−12 bodů za 90 dní“), vlevo popis `meta` a stav `status` (štítek s tečkou). `value` null = „–“
 * a prázdný kruh (hodnocení zatím není). Pod tím samostatná karta se složkami `components` a nadpisem
 * `componentsTitle`.
 */
final class WidgetScore
{
	/** @param list<WidgetScoreComponent> $components */
	public function __construct(
		public readonly int|float|null $value = null,
		public readonly int|float $max = 100,
		public readonly WidgetTone $tone = WidgetTone::Neutral,
		public readonly ?string $change = null,
		public readonly ?string $meta = null,
		public readonly ?WidgetBadge $status = null,
		public readonly array $components = [],
		public readonly ?string $componentsTitle = null,
	) {
	}

	/** Podíl skóre (0–1) pro kruh. */
	public function getRatio(): float
	{
		return $this->value === null || $this->max <= 0 ? 0.0 : max(0.0, min(1.0, $this->value / $this->max));
	}
}
