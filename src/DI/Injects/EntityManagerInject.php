<?php

namespace ADT\FancyAdmin\DI\Injects;

use Doctrine\ORM\EntityManagerInterface;
use Kdyby\Autowired\Attributes\Autowire;

trait EntityManagerInject
{
	#[Autowire]
	protected EntityManagerInterface $_em;
}