<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Services\JsComponents;
use Kdyby\Autowired\Attributes\Autowire;

trait JsComponentsInject
{
	#[Autowire]
	protected JsComponents $_jsComponents;
}