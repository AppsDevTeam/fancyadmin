<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Defaults\Customer;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\Traits\TMenuItemsTrait;

/** Výchozí menu modulu, dokud projekt nemá vlastní NavbarMenuFactory vedle svých presenterů */
class NavbarMenuFactory implements \ADT\FancyAdmin\Model\Menu\NavbarMenuFactory
{
	use TMenuItemsTrait;

	public function create(): NavbarMenu
	{
		return new NavbarMenu();
	}
}
