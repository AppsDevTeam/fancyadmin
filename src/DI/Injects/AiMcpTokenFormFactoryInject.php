<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\AiMcpToken\AiMcpTokenFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait AiMcpTokenFormFactoryInject
{
	#[Autowire]
	protected AiMcpTokenFormFactory $_aiMcpTokenFormFactory;
}
