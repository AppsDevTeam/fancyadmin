<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenu;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuItem;
use Nette\Security\Resource;

trait TMenuItemsTrait {

	public function addPermissionsItem(NavbarMenu|NavbarSubmenu $menu): void {
		$menu->addMenuItem(
			$this->createMenuItemEntity($menu)
				->setLabel('Permissions')
				->setLink('Permissions:default')
		);
	}

	public function addAclRolesItem(NavbarMenu|NavbarSubmenu $menu): void {
		$menu->addMenuItem(
			$this->createMenuItemEntity($menu)
				->setLabel('AclRoles')
				->setLink('AclRoles:default')
		);
	}

	public function addIdentitiesItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'Identities',
		string $link = 'Identities:default',
		?string $faIcon = null,
		?Resource $alcResource = null,
	): void {
		$navbarMenuItem = $this->createMenuItemEntity($menu)
			->setLabel($label)
			->setLink($link)
			->setAclResource($alcResource);

		if ($faIcon) {
			$navbarMenuItem->setFaIcon($faIcon);
		}

		$menu->addMenuItem($navbarMenuItem);
	}

	public function addAccountsItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'Accounts',
		string $link = 'Accounts:default',
		?string $faIcon = null,
		?Resource $alcResource = null,
	): void {
		$navbarMenuItem = $this->createMenuItemEntity($menu)
			->setLabel($label)
			->setLink($link)
			->setAclResource($alcResource);

		if ($faIcon) {
			$navbarMenuItem->setFaIcon($faIcon);
		}

		$menu->addMenuItem($navbarMenuItem);
	}

	public function addConfigurationsItem(NavbarMenu|NavbarSubmenu $menu): void {
		$menu->addMenuItem(
			$this->createMenuItemEntity($menu)
				->setLabel('Configurations')
				->setLink('Configurations:default')
		);
	}

	/**
	 * @param NavbarMenu|NavbarSubmenu $menu
	 * @return ($menu is NavbarMenu ? NavbarMenuItem : NavbarSubmenuItem)
	 */
	private function createMenuItemEntity(NavbarMenu|NavbarSubmenu $menu): NavbarMenuItem|NavbarSubmenuItem
	{
		return $menu instanceof NavbarMenu
			? new NavbarMenuItem()
			: new NavbarSubmenuItem();
	}
}