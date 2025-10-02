<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use Kdyby\Autowired\Attributes\Autowire;

trait AuthenticatorInject
{
	#[Autowire]
	protected DoctrineAuthenticator $_authenticator;
}