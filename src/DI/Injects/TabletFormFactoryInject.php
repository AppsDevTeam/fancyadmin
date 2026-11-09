<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\Tablet\TabletFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait TabletFormFactoryInject
{
	#[Autowire]
	protected TabletFormFactory $_tabletFormFactory;
}
