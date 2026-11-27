<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Položka widgetu `alerts`: text, šedý podtitul, hodnota vpravo a proklik. Tón určuje závažnost
 * (Warning = varování, Danger = kritické; ikona i podbarvení řádku). Štítky `badges` jsou v kartě
 * (AlertsWidget::setCards) za textem, jinde vpravo před hodnotou; `aside` je šedý doplněk vpravo dole
 * v kartě („hranice 60 dní“).
 */
final class WidgetAlert
{
	/** @param list<WidgetBadge> $badges */
	public function __construct(
		public readonly string $text,
		public readonly WidgetTone $tone = WidgetTone::Warning,
		public readonly ?string $note = null,
		public readonly int|float|string|null $value = null,
		public readonly ?string $link = null,
		public readonly ?string $linkLabel = null,
		public readonly array $badges = [],
		public readonly ?string $aside = null,
	) {
		foreach ($badges as $_badge) {
			if (!$_badge instanceof WidgetBadge) {
				throw new \InvalidArgumentException('Štítky upozornění musí být ' . WidgetBadge::class . '.');
			}
		}
	}
}
