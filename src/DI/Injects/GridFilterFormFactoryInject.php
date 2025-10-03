<?php

namespace ADT\FancyAdmin\DI\Injects;

use App\UI\Portal\Components\Forms\GridFilter\GridFilterFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait GridFilterFormFactoryInject
{
	#[Autowire]
	public GridFilterFormFactory $_gridFilterFormFactory;
}