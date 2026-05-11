<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\ChangeLogQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ChangeLogQueryFactoryInject
{
	#[Autowire]
	protected ChangeLogQueryFactory $_changeLogQueryFactory;
}
