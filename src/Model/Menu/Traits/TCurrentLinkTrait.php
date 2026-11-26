<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use Nette\Application\UI\Component;
use Nette\Application\UI\Presenter;

trait TCurrentLinkTrait
{
	protected bool $exactLink = false;

	/** @var list<string> jména akcí presenteru odkazu, ne odkazy */
	protected array $alsoCurrentFor = [];

	/** @var list<string> krátká jména presenterů bez modulu */
	protected array $alsoCurrentForPresenters = [];

	/**
	 * Položka svítí jen při přesné shodě odkazu - když na jeden presenter vede víc položek bez parametrů
	 * (Nastavení: Settings:product, Settings:category, ...), jinak by svítily všechny najednou.
	 */
	public function setExactLink(bool $exactLink = true): static
	{
		$this->exactLink = $exactLink;
		return $this;
	}

	/**
	 * Navazující akce presenteru odkazu, na kterých položka svítí taky (seznam i při editaci). Porovnává se
	 * jméno akce, ne odkaz - odkaz na akci s povinným parametrem by se pro každou položku sestavoval s dotazem.
	 */
	public function setAlsoCurrentFor(string ...$actions): static
	{
		$this->alsoCurrentFor = array_values($actions);
		return $this;
	}

	/** Jiné presentery, na jejichž libovolné akci položka svítí. */
	public function setAlsoCurrentForPresenters(string ...$presenters): static
	{
		$this->alsoCurrentForPresenters = array_values($presenters);
		return $this;
	}

	/**
	 * Položka bez parametrů svítí na všech akcích svého presenteru: odkaz na seznam
	 * (Patient:default) zůstane aktivní i na detailu záznamu (Patient:detail).
	 * Položka s parametry (víc položek na jeden presenter, např. Devices:default
	 * s type=ecr a type=terminal) nebo s setExactLink() svítí jen při přesné shodě.
	 */
	protected function isMenuLinkCurrent(Component $presenter, ?string $link, array $linkArgs): bool
	{
		if ($presenter instanceof Presenter && $this->isAlsoCurrent($presenter, $link)) {
			return true;
		}

		// Bez odkazu by isLinkCurrent(null) vrátil příznak naposledy vygenerovaného odkazu
		if ($link === null || $link === '#') {
			return false;
		}

		if (!$linkArgs && !$this->exactLink && preg_match('~^(.+:)[a-zA-Z0-9]+$~', $link, $m)) {
			return $presenter->isLinkCurrent($m[1] . '*');
		}

		return $presenter->isLinkCurrent($link, $linkArgs);
	}

	private function isAlsoCurrent(Presenter $presenter, ?string $link): bool
	{
		foreach ($this->alsoCurrentForPresenters as $name) {
			if (str_ends_with($presenter->getName(), ':' . $name)) {
				return true;
			}
		}

		if (!$this->alsoCurrentFor || $link === null) {
			return false;
		}

		$parts = explode(':', ltrim($link, ':'));
		$presenterName = $parts[count($parts) - 2] ?? null;

		return $presenterName !== null
			&& str_ends_with($presenter->getName(), ':' . $presenterName)
			&& in_array($presenter->getAction(), $this->alsoCurrentFor, true);
	}
}
