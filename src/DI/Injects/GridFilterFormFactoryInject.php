<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\Datagrid\Component\GridFilter\GridFilterFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait GridFilterFormFactoryInject
{
	#[Autowire]
	protected GridFilterFormFactory $_gridFilterFormFactory;
}