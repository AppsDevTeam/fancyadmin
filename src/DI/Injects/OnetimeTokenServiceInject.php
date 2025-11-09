<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Services\OnetimeTokenService;
use Kdyby\Autowired\Attributes\Autowire;

trait OnetimeTokenServiceInject
{
	#[Autowire]
	protected OnetimeTokenService $_onetimeTokenService;
}