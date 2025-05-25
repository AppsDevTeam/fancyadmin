<?php

namespace ADT\FancyAdmin\Model\Menu;

use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\LinkGenerator;

interface NavbarMenuFactory
{
	public function create(): NavbarMenu;
}