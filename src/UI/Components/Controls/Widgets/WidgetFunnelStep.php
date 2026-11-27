<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Krok trychtýře (FunnelWidget): popisek a hodnota v rámečku; `conversion` je přechod z předchozího kroku
 * („25 %“) na šipce před krokem v barvě `tone`, pod ní šedá `note` („cíl 28 %“).
 */
final class WidgetFunnelStep
{
	public function __construct(
		public readonly string $label,
		public readonly int|float|string|null $value,
		public readonly ?string $conversion = null,
		public readonly WidgetTone $tone = WidgetTone::Primary,
		public readonly ?string $note = null,
	) {
	}
}
