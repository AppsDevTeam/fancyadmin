<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\UI\Components\Forms\ChangePassword\ChangePasswordFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

trait ChangePasswordFormFactoryInject
{
	#[Autowire]
	protected ChangePasswordFormFactory $_changePasswordFormFactory;
}
