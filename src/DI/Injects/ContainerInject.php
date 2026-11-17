<?php

namespace ADT\FancyAdmin\DI\Injects;

use Kdyby\Autowired\Attributes\Autowire;
use Nette\DI\Container;

trait ContainerInject
{
	#[Autowire]
	protected Container $_container;
}
