<?php

namespace ADT\FancyAdmin\DI\Injects;

use App\Model\Queries\Factories\GridFilterQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait GridFilterQueryFactoryInject
{
	#[Autowire]
	public GridFilterQueryFactory $_gridFilterQueryFactory;
}