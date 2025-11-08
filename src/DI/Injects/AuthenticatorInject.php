<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\Authenticator;
use Kdyby\Autowired\Attributes\Autowire;

trait AuthenticatorInject
{
	#[Autowire]
	protected Authenticator $_authenticator;
}