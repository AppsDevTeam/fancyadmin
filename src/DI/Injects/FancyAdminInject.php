<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\FancyAdmin;
use Kdyby\Autowired\Attributes\Autowire;

trait FancyAdminInject
{
	#[Autowire]
	protected FancyAdmin $_fancyAdmin;
}