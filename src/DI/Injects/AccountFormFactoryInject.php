<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\Account\AccountFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AccountFormFactoryInject
{
	#[Autowire]
	protected AccountFormFactory $_accountFormFactory;
}