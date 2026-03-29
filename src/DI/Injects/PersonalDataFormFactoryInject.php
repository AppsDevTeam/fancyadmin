<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\PersonalData\PersonalDataFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait PersonalDataFormFactoryInject
{
	#[Autowire]
	protected PersonalDataFormFactory $_personalDataFormFactory;
}
