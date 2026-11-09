<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\WebPush\WebPushSender;
use Kdyby\Autowired\Attributes\Autowire;

trait WebPushSenderInject
{
	#[Autowire]
	protected WebPushSender $_webPushSender;
}
