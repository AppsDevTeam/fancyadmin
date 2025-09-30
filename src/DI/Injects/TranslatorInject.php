<?php

namespace ADT\FancyAdmin\DI\Injects;

use Nette\Localization\Translator;

trait TranslatorInject
{
	protected Translator $_translator;
	public function injectTranslator(Translator $_translator): void
	{
		$this->_translator = $_translator;
	}
}