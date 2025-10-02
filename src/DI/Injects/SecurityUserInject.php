<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\SecurityUser;
use Kdyby\Autowired\Attributes\Autowire;

trait SecurityUserInject
{
	#[Autowire]
	protected SecurityUser $_securityUser;
}