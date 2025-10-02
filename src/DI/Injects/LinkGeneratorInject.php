<?php

namespace ADT\FancyAdmin\DI\Injects;

use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\LinkGenerator;

trait LinkGeneratorInject
{
	#[Autowire]
	protected LinkGenerator $_linkGenerator;
}