<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\Identity\IdentityFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait IdentityFormFactoryInject
{
	#[Autowire]
	protected IdentityFormFactory $_identityFormFactory;
}