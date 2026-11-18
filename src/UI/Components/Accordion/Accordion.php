<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Accordion;

/**
 * Sekce accordionu (docs/accordion.md) - jen šablona, chování nese assets/js/accordion.js.
 * Šablona se do stránky importuje `{import ADT\FancyAdmin\UI\Components\Accordion\Accordion::TEMPLATE}`.
 */
final class Accordion
{
	public const TEMPLATE = __DIR__ . '/accordion.latte';
}
