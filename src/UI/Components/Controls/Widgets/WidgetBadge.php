<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/** Štítek widgetu: počet, změna („↑ 8,4 %“), stav. `title` je vysvětlení v nativní bublině. */
final class WidgetBadge
{
	public function __construct(
		public readonly string $text,
		public readonly WidgetTone $tone = WidgetTone::Neutral,
		public readonly ?string $title = null,
	) {
	}
}
