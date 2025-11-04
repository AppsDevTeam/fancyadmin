<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\DoctrineComponents\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Kdyby\Autowired\Attributes\Autowire;

trait EntityManagerInject
{
	#[Autowire]
	protected EntityManager $_em;
}