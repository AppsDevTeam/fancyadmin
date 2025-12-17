<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use Nette\Security\Resource;

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

	public function addIdentitiesItem(
		NavbarMenu $menu,
		string $label = 'Identities',
		string $link = 'Identities:default',
		?string $faIcon = null,
		?Resource $alcResource = null,
	): void {
		$navbarMenuItem = new NavbarMenuItem()
			->setLabel($label)
			->setLink($link)
			->setAclResource($alcResource);

		if ($faIcon) {
			$navbarMenuItem->setFaIcon($faIcon);
		}

		$menu->addMenuItem($navbarMenuItem);
	}

	public function addAccountsItem(
		NavbarMenu $menu,
		string $label = 'Accounts',
		string $link = 'Accounts:default',
		?string $faIcon = null,
		?Resource $alcResource = null,
	): void {
		$navbarMenuItem = new NavbarMenuItem()
			->setLabel($label)
			->setLink($link)
			->setAclResource($alcResource);

		if ($faIcon) {
			$navbarMenuItem->setFaIcon($faIcon);
		}

		$menu->addMenuItem($navbarMenuItem);
	}

	public function addConfigurationsItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Configurations')
				->setLink('Configurations:default')
		);
	}
}