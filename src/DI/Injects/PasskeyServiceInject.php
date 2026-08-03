<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use Kdyby\Autowired\Attributes\Autowire;

trait PasskeyServiceInject
{
	#[Autowire]
	protected PasskeyService $_passkeyService;
}
