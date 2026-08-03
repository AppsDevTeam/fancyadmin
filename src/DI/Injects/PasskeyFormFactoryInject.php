<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\Passkey\PasskeyFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait PasskeyFormFactoryInject
{
	#[Autowire]
	protected PasskeyFormFactory $_passkeyFormFactory;
}
