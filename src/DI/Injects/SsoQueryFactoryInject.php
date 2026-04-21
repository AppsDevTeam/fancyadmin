<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\SsoQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait SsoQueryFactoryInject
{
	#[Autowire]
	protected SsoQueryFactory $_ssoQueryFactory;
}
