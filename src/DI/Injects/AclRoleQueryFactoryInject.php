<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AclRoleQueryFactoryInject
{
	#[Autowire]
	protected AclRoleQueryFactory $_aclRoleQueryFactory;
}