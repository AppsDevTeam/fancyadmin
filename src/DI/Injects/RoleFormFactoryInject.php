<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use ADT\FancyAdmin\UI\Components\Forms\Role\RoleFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait RoleFormFactoryInject
{
	#[Autowire]
	protected RoleFormFactory $_roleFormFactory;
}