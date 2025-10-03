<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait GridFilterQueryFactoryInject
{
	#[Autowire]
	protected GridFilterQueryFactory $_gridFilterQueryFactory;
}