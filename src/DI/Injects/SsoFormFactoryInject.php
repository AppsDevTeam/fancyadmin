<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\Sso\SsoFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait SsoFormFactoryInject
{
	#[Autowire]
	protected SsoFormFactory $_ssoFormFactory;
}
