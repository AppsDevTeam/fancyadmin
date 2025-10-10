<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\AclRole\AclRoleFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AclRoleFormFactoryInject
{
	#[Autowire]
	protected AclRoleFormFactory $_roleFormFactory;
}