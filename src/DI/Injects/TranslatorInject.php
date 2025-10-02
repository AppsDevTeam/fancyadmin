<?php

namespace ADT\FancyAdmin\DI\Injects;

use Kdyby\Autowired\Attributes\Autowire;
use Nette\Localization\Translator;

trait TranslatorInject
{
	#[Autowire]
	protected Translator $_translator;
}