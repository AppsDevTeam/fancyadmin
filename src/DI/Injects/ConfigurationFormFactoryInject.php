<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\Configuration\ConfigurationFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ConfigurationFormFactoryInject
{
	#[Autowire]
	protected ConfigurationFormFactory $_configurationFormFactory;
}