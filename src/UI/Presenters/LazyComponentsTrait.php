<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

/**
 * Komponenty (formuláře v modalech) sestavené až při otevření modalu, ne s každým zobrazením
 * stránky (docs/lazy-modal.md). Šablona je obalí snippetem lazy<Name> a vykreslí, jen když
 * isLazyComponentLoaded() - jinak jen „Načítám…"; modal s data-lazy-url = {link lazyComponent!,
 * name: <name>} si je načte assets/js/lazyModal.js.
 *
 * Akce stránky u signálu lazyComponent! běží (komponenta z ní obvykle potřebuje záznam),
 * zbytek stránky v ní i v renderu přeskoč přes isLazyComponentSignal().
 */
trait LazyComponentsTrait
{
	private ?string $requestedLazyComponent = null;

	/** @return list<string> komponenty, které se načítají až při otevření */
	abstract protected function getLazyComponents(): array;

	/**
	 * Vykreslit komponentu: modal ji právě načítá, nebo už existuje (odeslání formuláře
	 * s chybou). Projekt přidá případy, kdy se modal otevírá rovnou (úprava záznamu z URL).
	 */
	public function isLazyComponentLoaded(string $name): bool
	{
		return $name === $this->requestedLazyComponent
			|| array_key_exists($name, iterator_to_array($this->getComponents()));
	}

	public function handleLazyComponent(string $name): void
	{
		if (!in_array($name, $this->getLazyComponents(), true)) {
			$this->error();
		}
		$this->requestedLazyComponent = $name;
		$this->redrawControl('lazy' . ucfirst($name));
	}

	protected function isLazyComponentSignal(): bool
	{
		return $this->getSignal() === ['', 'lazyComponent'];
	}
}
