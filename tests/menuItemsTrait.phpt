<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenu;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuItem;
use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Model\Menu\Traits\TMenuItemsTrait;
use Tester\Assert;

/**
 * TMenuItemsTrait - hotove polozky menu pro obrazovky, ktere dodava balicek.
 */

require __DIR__ . '/bootstrap.php';


class MenuFactoryWithTrait
{
	use TMenuItemsTrait;
}


/** @return array{0: string, 1: string} popisek a odkaz vychozi polozky */
function defaults(string $method): array
{
	$parameters = new ReflectionMethod(MenuFactoryWithTrait::class, $method)->getParameters();

	return [$parameters[1]->getDefaultValue(), $parameters[2]->getDefaultValue()];
}


test('vychozi polozky miri na obrazovky balicku', function () {
	Assert::same(['fcadmin.presenters.permissions.title', 'Acl:default'], defaults('addAclItem'));
	Assert::same(['fcadmin.presenters.roles.title', 'AclRoles:default'], defaults('addAclRolesItem'));
	Assert::same(['fcadmin.presenters.identity.title', 'Identities:default'], defaults('addIdentitiesItem'));
	Assert::same(['fcadmin.presenters.account.title', 'Accounts:default'], defaults('addAccountsItem'));
	Assert::same(['fcadmin.presenters.configurations.title', 'Configurations:default'], defaults('addConfigurationsItem'));
	Assert::same(['fcadmin.presenters.changeLogs.title', 'ChangeLogs:default'], defaults('addChangeLogsItem'));
});


test('do hlavniho menu se prida polozka menu', function () {
	$factory = new MenuFactoryWithTrait();
	$menu = new NavbarMenu();

	$factory->addIdentitiesItem($menu);

	Assert::count(1, $menu->getMenuItems());
	$item = $menu->getMenuItems()[0];
	Assert::type(NavbarMenuItem::class, $item);
	Assert::same('fcadmin.presenters.identity.title', $item->getLabel());
	Assert::same('Identities:default', $item->getLink());
	// Bez zdroje se doplni az pri resolveAclResources().
	Assert::null($item->getAclResource());
	// Ikona zustane vychozi, dokud se nepreda.
	Assert::same('chart-simple', $item->getFaIcon());
});


test('do podmenu se prida polozka podmenu', function () {
	$factory = new MenuFactoryWithTrait();
	$submenu = new NavbarSubmenu(new NavbarMenuItem()->setLabel('Sprava'));

	$factory->addAclRolesItem($submenu);

	Assert::count(1, $submenu->getSubMenuItems());
	Assert::type(NavbarSubmenuItem::class, $submenu->getSubMenuItems()[0]);
	Assert::same('AclRoles:default', $submenu->getSubMenuItems()[0]->getLink());
});


test('popisek, odkaz, ikona i zdroj jdou prebit', function () {
	$factory = new MenuFactoryWithTrait();
	$menu = new NavbarMenu();
	$resource = new StringResource('vlastni.zdroj');

	$factory->addAccountsItem($menu, 'Zakaznici', 'Customers:default', 'building', $resource);

	$item = $menu->getMenuItems()[0];
	Assert::same('Zakaznici', $item->getLabel());
	Assert::same('Customers:default', $item->getLink());
	Assert::same('building', $item->getFaIcon());
	Assert::same($resource, $item->getAclResource());
});


test('vsechny hotove polozky jdou pridat najednou', function () {
	$factory = new MenuFactoryWithTrait();
	$menu = new NavbarMenu();

	$factory->addAclItem($menu);
	$factory->addAclRolesItem($menu);
	$factory->addIdentitiesItem($menu);
	$factory->addAccountsItem($menu);
	$factory->addConfigurationsItem($menu);
	$factory->addChangeLogsItem($menu);

	Assert::same(
		['Acl:default', 'AclRoles:default', 'Identities:default', 'Accounts:default', 'Configurations:default', 'ChangeLogs:default'],
		array_map(fn(NavbarMenuItem $item) => $item->getLink(), $menu->getMenuItems()),
	);
});
