<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Entities\Enums\IdentityScopeEnum;
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

	/**
	 * Se $scope odkazuje jen na cast seznamu uzivatelu (bezni uzivatele / spravci),
	 * takze polozka muze byt v menu dvakrat - viz IdentityScopeEnum. Bez nej miri
	 * na cely seznam.
	 */
	public function addIdentitiesItem(
		NavbarMenu|NavbarSubmenu $menu,
		?string $label = null,
		string $link = 'Identities:default',
		?string $faIcon = null,
		?Resource $alcResource = null,
		?IdentityScopeEnum $scope = null,
	): void {
		$navbarMenuItem = $this->createMenuItemEntity($menu)
			->setLabel($label ?? $scope?->titleKey() ?? 'fcadmin.presenters.identity.title')
			->setLink($link)
			->setAclResource($alcResource);

		if ($scope) {
			$navbarMenuItem->setLinkArgs(['scope' => $scope->value]);
		}

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

	public function addAuthLogsItem(
		NavbarMenu|NavbarSubmenu $menu,
		string $label = 'fcadmin.presenters.authLogs.title',
		string $link = 'AuthLogs:default',
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