<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\AuthLogQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AuthLogQueryFactoryInject
{
	#[Autowire]
	protected AuthLogQueryFactory $_authLogQueryFactory;
}
