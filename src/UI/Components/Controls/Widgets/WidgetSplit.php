<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Část pruhu poměru v KPI dlaždici (KpiWidget::setSplit) - např. prodej / pronájem, exkluzivní /
 * neexkluzivní. Šířka v pruhu podle `value`, v legendě tečka, popisek, `display` (formátovaná hodnota,
 * jinak `value`) a podíl. S `note` / `extra` je legenda řádková: popisek a hodnota, poznámka, doplněk vpravo
 * v barvě tónu (např. „29 % vůči novým náborům“, „3,11 mil. Kč“).
 */
final class WidgetSplit
{
	public function __construct(
		public readonly string $label,
		public readonly int|float $value,
		public readonly ?string $display = null,
		public readonly WidgetTone $tone = WidgetTone::Primary,
		public readonly ?string $note = null,
		public readonly ?string $extra = null,
	) {
	}
}
