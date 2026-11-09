<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Tablet\TabletService;
use Kdyby\Autowired\Attributes\Autowire;

trait TabletServiceInject
{
	#[Autowire]
	protected TabletService $_tabletService;
}
