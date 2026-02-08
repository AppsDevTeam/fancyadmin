<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\DoctrineAuthenticator\OTP\OnetimeTokenQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait OnetimeTokenQueryFactoryInject
{
	#[Autowire]
	protected OnetimeTokenQueryFactory $_onetimeTokenQueryFactory;
}