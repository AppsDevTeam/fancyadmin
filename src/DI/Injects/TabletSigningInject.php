<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Tablet\TabletSigning;
use Kdyby\Autowired\Attributes\Autowire;

trait TabletSigningInject
{
	#[Autowire]
	protected TabletSigning $_tabletSigning;
}
