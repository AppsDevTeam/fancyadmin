<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\BackgroundQueue\BackgroundQueue;
use Kdyby\Autowired\Attributes\Autowire;

trait BackgroundQueryInject
{
	#[Autowire]
	public BackgroundQueue $_backgroundQueue;
}