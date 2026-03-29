<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\BreachedPasswordChecker;
use Kdyby\Autowired\Attributes\Autowire;

trait BreachedPasswordCheckerInject
{
	#[Autowire]
	protected BreachedPasswordChecker $_breachedPasswordChecker;
}
