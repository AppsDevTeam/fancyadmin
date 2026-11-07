<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use Nette\Application\UI\Component;

trait TCurrentLinkTrait
{
	/**
	 * Položka bez parametrů svítí na všech akcích svého presenteru: odkaz na seznam
	 * (Patient:default) zůstane aktivní i na detailu záznamu (Patient:detail).
	 * Položka s parametry (víc položek na jeden presenter, např. Devices:default
	 * s type=ecr a type=terminal) svítí jen při přesné shodě, jinak by svítily všechny.
	 */
	protected function isMenuLinkCurrent(Component $presenter, ?string $link, array $linkArgs): bool
	{
		// Bez odkazu by isLinkCurrent(null) vrátil příznak naposledy vygenerovaného odkazu
		if ($link === null || $link === '#') {
			return false;
		}

		if (!$linkArgs && preg_match('~^(.+:)[a-zA-Z0-9]+$~', $link, $m)) {
			return $presenter->isLinkCurrent($m[1] . '*');
		}

		return $presenter->isLinkCurrent($link, $linkArgs);
	}
}
