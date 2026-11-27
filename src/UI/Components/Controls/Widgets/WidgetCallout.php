<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Obsah zvýrazněné karty (CalloutWidget): výrazný `headline` („Nabírá dost, ale málo rezervuje“), vysvětlení
 * `text` a pod nadpisem `itemsTitle` seznam položek (`WidgetAlert`: text, hodnota vpravo v barvě tónu, odkaz).
 */
final class WidgetCallout
{
	/** @param list<WidgetAlert> $items */
	public function __construct(
		public readonly string $headline,
		public readonly ?string $text = null,
		public readonly array $items = [],
		public readonly ?string $itemsTitle = null,
	) {
	}
}
