<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenu;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuItem;
use Nette\Security\Resource;

trait TMenuItemsTrait {

	public function addAclItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'fcadmin.presenters.permissions.title',
		string $link = 'Acl:default',
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

	public function addAclRolesItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'fcadmin.presenters.roles.title',
		string $link = 'AclRoles:default',
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

	public function addIdentitiesItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'fcadmin.presenters.identity.title',
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
		string $label = 'fcadmin.presenters.account.title',
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

	public function addConfigurationsItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'fcadmin.presenters.configurations.title',
		string $link = 'Configurations:default',
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

	public function addChangeLogsItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'fcadmin.presenters.changeLogs.title',
		string $link = 'ChangeLogs:default',
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