<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\DoctrineAuthenticator\OTP\OnetimeTokenService;
use Kdyby\Autowired\Attributes\Autowire;

trait OnetimeTokenServiceInject
{
	#[Autowire]
	protected OnetimeTokenService $_onetimeTokenService;
}