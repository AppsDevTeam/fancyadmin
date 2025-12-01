<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;

trait TMenuItemsTrait {

	public function addPermissionsItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Permissions')
				->setLink('Permissions:default')
		);
	}

	public function addRoleItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Roles')
				->setLink('Roles:default')
		);
	}

	public function addIdentitiesItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Identities')
				->setLink('Identities:default')
		);
	}

	public function addAccountsItem(NavbarMenu $menu, string $label = 'Accounts', string $link = 'Accounts:default'): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel($label)
				->setLink($link)
		);
	}

	public function addConfigurationsItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Configurations')
				->setLink('Configurations:default')
		);
	}
}