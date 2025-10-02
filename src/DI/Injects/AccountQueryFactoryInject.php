<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AccountQueryFactoryInject
{
	#[Autowire]
	protected AccountQueryFactory $_accountQueryFactory;
}