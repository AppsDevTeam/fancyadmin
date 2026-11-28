<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Defaults\Backoffice;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenu;
use ADT\FancyAdmin\Model\Menu\Traits\TMenuItemsTrait;

/** Výchozí menu modulu, dokud projekt nemá vlastní NavbarMenuFactory vedle svých presenterů */
class NavbarMenuFactory implements \ADT\FancyAdmin\Model\Menu\NavbarMenuFactory
{
	use TMenuItemsTrait;

	public function create(): NavbarMenu
	{
		$menu = new NavbarMenu();
		$menu->addMenuItem(
			new NavbarMenuItem()
				->setLabel('fcadmin.defaults.menu.administration')
				->setFaIcon('gear')
				->setupSubmenuItems(function (NavbarSubmenu $navbarSubmenu): void {
					$this->addAccountsItem($navbarSubmenu);
					$this->addAclItem($navbarSubmenu);
					$this->addAclRolesItem($navbarSubmenu);
					$this->addIdentitiesItem($navbarSubmenu);
					$this->addChangeLogsItem($navbarSubmenu);
				})
		);

		return $menu;
	}
}
