<?php


namespace ADT\FancyAdmin\Model\Services;

use Symfony\Component\Translation\TranslatorBagInterface;

class JsComponents extends \ADT\Utils\JsComponents
{
	/**
	 * Výchozí nastavení builtinů adt-js-components, které projekt přepíše svým `jsComponentsConfig`.
	 * Tom Select (builtin Select) dostane každý select kromě nativních - select-default
	 * a selecty kalendáře flatpickru, jejichž seznam by se otvíral mimo kalendář.
	 */
	public const array DEFAULTS = [
		'select' => ['selector' => 'select:not(.select-default)'],
	];

	public function setProjectComponents(array $components): self
	{
		return $this->setComponents(array_replace_recursive(self::DEFAULTS, $components));
	}

	public function setTranslateConfig(TranslatorBagInterface $translator): void
	{
		$this->components['translate'] = [
			'all' => $translator->getCatalogue()->all('appJs')
		];
	}
}
