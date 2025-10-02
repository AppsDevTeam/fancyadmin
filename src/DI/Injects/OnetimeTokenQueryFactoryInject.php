<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\OnetimeTokenQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait OnetimeTokenQueryFactoryInject
{
	#[Autowire]
	protected OnetimeTokenQueryFactory $_onetimeTokenQueryFactory;
}