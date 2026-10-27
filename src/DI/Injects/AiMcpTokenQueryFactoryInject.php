<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Queries\Factories\AiMcpTokenQueryFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AiMcpTokenQueryFactoryInject
{
	#[Autowire]
	protected AiMcpTokenQueryFactory $_aiMcpTokenQueryFactory;
}
