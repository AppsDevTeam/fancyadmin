<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Quickbar\QuickbarSettings;
use Kdyby\Autowired\Attributes\Autowire;

trait QuickbarSettingsInject
{
	#[Autowire]
	protected QuickbarSettings $_quickbarSettings;
}
