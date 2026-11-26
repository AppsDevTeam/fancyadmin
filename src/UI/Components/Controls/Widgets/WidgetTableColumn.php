<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Sloupec widgetu `table`; číselný se formátuje a zarovná doprava i s hlavičkou. `wrap` zalomí dlouhý text
 * (tabulka má jinak jako gridy `white-space: nowrap` a dlouhý text by ji roztáhl do šířky). `group` je nadpis
 * skupiny nad sloupcem - sousední sloupce se stejnou skupinou mají jeden společný („Realizované · tržba“).
 */
final class WidgetTableColumn
{
	public function __construct(
		public readonly string $label,
		public readonly bool $numeric = false,
		public readonly bool $wrap = false,
		public readonly ?string $group = null,
	) {
	}
}
