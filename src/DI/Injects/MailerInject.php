<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Mailer\Mailer;
use Kdyby\Autowired\Attributes\Autowire;

trait MailerInject
{
	#[Autowire]
	protected Mailer $_mailer;
}