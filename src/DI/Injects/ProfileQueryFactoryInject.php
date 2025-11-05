<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\ProfileQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ProfileQueryFactoryInject
{
	#[Autowire]
	protected ProfileQueryFactory $_profileQueryFactory;
}