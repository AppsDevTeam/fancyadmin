<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\ReturnPath;
use Kdyby\Autowired\Attributes\Autowire;

trait ReturnPathInject
{
	#[Autowire]
	protected ReturnPath $_returnPath;
}
