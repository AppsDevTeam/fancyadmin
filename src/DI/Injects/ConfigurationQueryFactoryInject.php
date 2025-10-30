<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ConfigurationQueryFactoryInject
{
	#[Autowire]
	protected ConfigurationQueryFactory $_configurationQueryFactory;
}