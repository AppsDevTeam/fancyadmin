<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ApiKeyFormFactoryInject
{
	#[Autowire]
	protected ApiKeyFormFactory $_apiKeyFormFactory;
}
