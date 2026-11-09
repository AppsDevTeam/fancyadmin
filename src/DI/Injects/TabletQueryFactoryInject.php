<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\TabletQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait TabletQueryFactoryInject
{
	#[Autowire]
	protected TabletQueryFactory $_tabletQueryFactory;
}
