<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Push\Push;
use Kdyby\Autowired\Attributes\Autowire;

trait PushInject
{
	#[Autowire]
	protected Push $_push;
}
