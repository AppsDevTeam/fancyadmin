<?php

namespace ADT\FancyAdmin\Model\Menu;

use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\LinkGenerator;

abstract class NavbarMenuFactory
{
	abstract public function create(): NavbarMenu;
}