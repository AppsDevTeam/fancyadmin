<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\ApiKeyQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ApiKeyQueryFactoryInject
{
	#[Autowire]
	protected ApiKeyQueryFactory $_apiKeyQueryFactory;
}
