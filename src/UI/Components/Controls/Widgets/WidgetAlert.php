<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Položka widgetu `alerts`: text, šedý podtitul, hodnota vpravo a proklik. Tón určuje závažnost
 * (Warning = varování, Danger = kritické; ikona i podbarvení řádku).
 */
final class WidgetAlert
{
	public function __construct(
		public readonly string $text,
		public readonly WidgetTone $tone = WidgetTone::Warning,
		public readonly ?string $note = null,
		public readonly int|float|string|null $value = null,
		public readonly ?string $link = null,
		public readonly ?string $linkLabel = null,
	) {
	}
}
