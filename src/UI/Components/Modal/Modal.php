<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Modal;

/**
 * Modal ve vzhledu fancyadminu (docs/modal.md) - jen šablona, otevírání řeší Bootstrap.
 * Šablona se do stránky importuje `{import ADT\FancyAdmin\UI\Components\Modal\Modal::TEMPLATE}`.
 */
final class Modal
{
	public const TEMPLATE = __DIR__ . '/modal.latte';
}
