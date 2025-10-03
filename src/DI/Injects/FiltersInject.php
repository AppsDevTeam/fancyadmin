<?php

namespace ADT\FancyAdmin\DI\Injects;

use App\Model\Filters;
use Kdyby\Autowired\Attributes\Autowire;

trait FiltersInject
{
	#[Autowire]
	public Filters $_filters;
}