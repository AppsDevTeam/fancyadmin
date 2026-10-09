<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use Nette\Application\UI\Control;

/**
 * Komponenta, ktera o "aktualnim odkazu" rozhoduje podle seznamu misto podle routeru -
 * testy menu tak nepotrebuji cely Nette Application.
 */
final class TestComponent extends Control
{
	/** @param list<string> $currentLinks */
	public function __construct(private readonly array $currentLinks = [])
	{
	}

	public function isLinkCurrent(?string $destination = null, $args = []): bool
	{
		return in_array($destination, $this->currentLinks, true);
	}
}
