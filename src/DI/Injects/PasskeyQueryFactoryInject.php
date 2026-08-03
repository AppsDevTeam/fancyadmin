<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait PasskeyQueryFactoryInject
{
	#[Autowire]
	protected PasskeyQueryFactory $_passkeyQueryFactory;
}
