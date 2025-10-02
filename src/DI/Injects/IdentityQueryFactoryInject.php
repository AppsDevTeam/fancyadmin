<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait IdentityQueryFactoryInject
{
	#[Autowire]
	protected IdentityQueryFactory $_identityQueryFactory;
}