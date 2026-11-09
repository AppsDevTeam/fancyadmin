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
		// Jako Nette: 'Presenter:*' = kterákoli akce presenteru
		if ($destination !== null && str_ends_with($destination, ':*')) {
			foreach ($this->currentLinks as $_link) {
				if (str_starts_with($_link, substr($destination, 0, -1))) {
					return true;
				}
			}
			return false;
		}

		return in_array($destination, $this->currentLinks, true);
	}
}
