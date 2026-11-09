<?php


namespace ADT\FancyAdmin\Model\Services;

use Symfony\Component\Translation\TranslatorBagInterface;

class JsComponents extends \ADT\Utils\JsComponents
{
	public function setTranslateConfig(TranslatorBagInterface $translator): void
	{
		$this->components['translate'] = [
			'all' => $translator->getCatalogue()->all('appJs')
		];
	}
}
