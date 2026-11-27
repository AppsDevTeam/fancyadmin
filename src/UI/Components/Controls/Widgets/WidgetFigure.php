<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Jeden údaj řady widgetu `figures` (KPI karty `kpi` s částmi): popisek, hodnota, jednotka za hodnotou,
 * šedý doplněk („z aktivních“) a štítky. Tón barví hodnotu (Neutral = běžný text).
 */
/** `link` = proklik údaje: šipka u popisku a odkazem je celý údaj (u dlaždic setCards celá dlaždice). */
final class WidgetFigure
{
	/** @param list<WidgetBadge> $badges */
	public function __construct(
		public readonly string $label,
		public readonly int|float|string|null $value,
		public readonly ?string $unit = null,
		public readonly ?string $note = null,
		public readonly WidgetTone $tone = WidgetTone::Neutral,
		public readonly ?string $title = null,
		public readonly array $badges = [],
		public readonly ?string $link = null,
	) {
		foreach ($badges as $_badge) {
			if (!$_badge instanceof WidgetBadge) {
				throw new \InvalidArgumentException('Štítky údaje musí být ' . WidgetBadge::class . '.');
			}
		}
	}
}
