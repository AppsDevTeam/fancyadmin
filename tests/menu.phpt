<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenu;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuHeading;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuItem;
use ADT\FancyAdmin\Model\Menu\StringResource;
use ADT\FancyAdmin\Model\Menu\UserMenu;
use ADT\FancyAdmin\Model\Menu\UserMenuItem;
use ADT\FancyAdmin\Tests\Fixtures\TestComponent;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use Nette\Security\Resource;
use Tester\Assert;

/**
 * Menu administrace - polozky, podmenu, viditelnost podle opravneni a odvozeni ACL zdroju.
 */

require __DIR__ . '/bootstrap.php';


test('zdroj z retezce se chova jako Resource', function () {
	$resource = new StringResource('portalBackoffice.orders');

	Assert::type(Resource::class, $resource);
	Assert::same('portalBackoffice.orders', $resource->getResourceId());
});


test('polozka menu ma vychozi popisek a ikonu', function () {
	$item = new NavbarMenuItem();

	Assert::same('Test', $item->getLabel());
	Assert::same('chart-simple', $item->getFaIcon());
	Assert::null($item->getLink());
	Assert::same([], $item->getLinkArgs());
	Assert::null($item->getSubmenu());
	Assert::null($item->getAclResource());
});


test('polozka menu se nastavuje retezenim', function () {
	$resource = new StringResource('portalBackoffice.orders');
	$item = new NavbarMenuItem()
		->setLabel('Objednavky')
		->setFaIcon('cart')
		->setLink('Orders:default')
		->setLinkArgs(['state' => 'new'])
		->setAclResource($resource);

	Assert::same('Objednavky', $item->getLabel());
	Assert::same('cart', $item->getFaIcon());
	Assert::same('Orders:default', $item->getLink());
	Assert::same(['state' => 'new'], $item->getLinkArgs());
	Assert::same($resource, $item->getAclResource());
});


test('menu drzi polozky v poradi, v jakem prisly', function () {
	$menu = new NavbarMenu();
	$prvni = new NavbarMenuItem()->setLabel('Prvni');
	$druha = new NavbarMenuItem()->setLabel('Druha');

	Assert::same([], $menu->getMenuItems());

	$menu->addMenuItem($prvni)->addMenuItem($druha);

	Assert::same([$prvni, $druha], $menu->getMenuItems());
});


test('podmenu se sklada pres setupSubmenuItems', function () {
	$item = new NavbarMenuItem()->setLabel('Sprava');

	$item->setupSubmenuItems(function (NavbarSubmenu $submenu) {
		$submenu->addHeading('Uzivatele');
		$submenu->addMenuItem(new NavbarSubmenuItem()->setLabel('Identity')->setLink('Identities:default'));
	});

	$submenu = $item->getSubmenu();
	Assert::type(NavbarSubmenu::class, $submenu);
	Assert::count(2, $submenu->getSubMenuItems());
	Assert::type(NavbarSubmenuHeading::class, $submenu->getSubMenuItems()[0]);
	Assert::same('Uzivatele', $submenu->getSubMenuItems()[0]->getLabel());
});


test('opakovane volani setupSubmenuItems podmenu doplnuje', function () {
	$item = new NavbarMenuItem();

	$item->setupSubmenuItems(fn(NavbarSubmenu $s) => $s->addMenuItem(new NavbarSubmenuItem()->setLabel('A')));
	$item->setupSubmenuItems(fn(NavbarSubmenu $s) => $s->addMenuItem(new NavbarSubmenuItem()->setLabel('B')));

	Assert::count(2, $item->getSubmenu()->getSubMenuItems());
});


test('podmenu prebira titulek od rodicovske polozky', function () {
	$parent = new NavbarMenuItem()->setLabel('Sprava');
	$submenu = new NavbarSubmenu($parent);

	Assert::same('Sprava', $submenu->getTitle());
	Assert::same('Vlastni', $submenu->setTitle('Vlastni')->getTitle());
	// Navrat na null znamena zase popisek rodice.
	Assert::same('Sprava', $submenu->setTitle(null)->getTitle());
});


test('polozka bez zdroje a podminky je videt vzdy', function () {
	$item = new NavbarMenuItem()->setLink('Orders:default');
	$user = new TestSecurityUser();

	Assert::true($item->isVisible($user, new TestComponent()));
});


test('polozka se zdrojem potrebuje opravneni', function () {
	$item = new NavbarMenuItem()->setAclResource(new StringResource('portalBackoffice.orders'));
	$component = new TestComponent();

	Assert::false($item->isVisible(new TestSecurityUser(), $component));
	Assert::true($item->isVisible(new TestSecurityUser(['portalBackoffice.orders']), $component));
	Assert::true($item->isVisible(new TestSecurityUser(isAdmin: true), $component));
});


test('podminka rozhoduje o viditelnosti a dostane uzivatele i komponentu', function () {
	$user = new TestSecurityUser();
	$component = new TestComponent();
	$zachyceno = [];

	$item = new NavbarMenuItem()->setCondition(function ($u, $c) use (&$zachyceno) {
		$zachyceno = [$u, $c];
		return false;
	});

	Assert::false($item->isVisible($user, $component));
	Assert::same([$user, $component], $zachyceno);
});


test('zdroj ma prednost pred podminkou', function () {
	// Bez opravneni se podminka uz nevola.
	$volano = false;
	$item = new NavbarMenuItem()
		->setAclResource(new StringResource('portalBackoffice.orders'))
		->setCondition(function () use (&$volano) {
			$volano = true;
			return true;
		});

	Assert::false($item->isVisible(new TestSecurityUser(), new TestComponent()));
	Assert::false($volano);
});


test('polozka s podmenu je povolena, kdyz je videt aspon jedna jeji polozka', function () {
	$item = new NavbarMenuItem();
	$item->setupSubmenuItems(function (NavbarSubmenu $submenu) {
		$submenu->addMenuItem(new NavbarSubmenuItem()->setLink('A:default')->setAclResource(new StringResource('a')));
		$submenu->addMenuItem(new NavbarSubmenuItem()->setLink('B:default')->setAclResource(new StringResource('b')));
	});
	$component = new TestComponent();

	Assert::false($item->isEnabledSubmenu(new TestSecurityUser(), $component));
	Assert::true($item->isEnabledSubmenu(new TestSecurityUser(['b']), $component));
});


test('nadpis v podmenu se do rozhodovani nepocita', function () {
	$item = new NavbarMenuItem();
	$item->setupSubmenuItems(fn(NavbarSubmenu $submenu) => $submenu->addHeading('Sekce'));

	Assert::false($item->isEnabledSubmenu(new TestSecurityUser(), new TestComponent()));
});


test('polozka bez podmenu je povolena', function () {
	$item = new NavbarMenuItem()->setLink('Orders:default');

	Assert::true($item->isEnabledSubmenu(new TestSecurityUser(), new TestComponent()));
});


test('vlastni zdroj polozky rozhodne i o podmenu', function () {
	$item = new NavbarMenuItem()->setAclResource(new StringResource('portalBackoffice.orders'));
	$item->setupSubmenuItems(fn(NavbarSubmenu $s) => $s->addMenuItem(new NavbarSubmenuItem()->setLink('A:default')));
	$component = new TestComponent();

	Assert::false($item->isEnabledSubmenu(new TestSecurityUser(), $component));
	Assert::true($item->isEnabledSubmenu(new TestSecurityUser(['portalBackoffice.orders']), $component));
});


test('nesplnena podminka zavre podmenu', function () {
	$item = new NavbarMenuItem()->setCondition(fn() => false);
	$item->setupSubmenuItems(fn(NavbarSubmenu $s) => $s->addMenuItem(new NavbarSubmenuItem()->setLink('A:default')));

	Assert::false($item->isEnabledSubmenu(new TestSecurityUser(), new TestComponent()));
});


test('aktualni je polozka, na jejiz odkaz se uzivatel diva', function () {
	$item = new NavbarMenuItem()->setLink('Orders:default');

	Assert::true($item->isCurrent(new TestComponent(['Orders:default'])));
	Assert::false($item->isCurrent(new TestComponent(['Identities:default'])));
});


test('polozka s podmenu je aktualni i podle sve podpolozky', function () {
	$item = new NavbarMenuItem()->setLink('Sprava:default');
	$item->setupSubmenuItems(function (NavbarSubmenu $submenu) {
		$submenu->addHeading('Sekce');
		$submenu->addMenuItem(new NavbarSubmenuItem()->setLink('Identities:default'));
	});

	Assert::true($item->isCurrent(new TestComponent(['Identities:default'])));
	Assert::false($item->isCurrent(new TestComponent(['Orders:default'])));
});


test('ACL zdroje se odvodi z modulu a nazvu presenteru', function () {
	$menu = new NavbarMenu();
	$menu->addMenuItem(new NavbarMenuItem()->setLink('Devices:default'));
	$menu->addMenuItem(new NavbarMenuItem()->setLink('Orders:detail'));

	$menu->resolveAclResources('PortalBackoffice');

	Assert::same('portalBackoffice.devices', $menu->getMenuItems()[0]->getAclResource()->getResourceId());
	Assert::same('portalBackoffice.orders', $menu->getMenuItems()[1]->getAclResource()->getResourceId());
});


test('rucne nastaveny zdroj se neprepisuje', function () {
	$resource = new StringResource('vlastni.zdroj');
	$menu = new NavbarMenu();
	$menu->addMenuItem(new NavbarMenuItem()->setLink('Devices:default')->setAclResource($resource));

	$menu->resolveAclResources('PortalBackoffice');

	Assert::same($resource, $menu->getMenuItems()[0]->getAclResource());
});


test('polozka bez odkazu zdroj nedostane', function () {
	$menu = new NavbarMenu();
	$menu->addMenuItem(new NavbarMenuItem()->setLabel('Jen rozcestnik'));

	$menu->resolveAclResources('PortalBackoffice');

	Assert::null($menu->getMenuItems()[0]->getAclResource());
});


test('zdroje se odvodi i pro polozky podmenu', function () {
	$item = new NavbarMenuItem()->setLabel('Sprava');
	$item->setupSubmenuItems(function (NavbarSubmenu $submenu) {
		$submenu->addHeading('Uzivatele');
		$submenu->addMenuItem(new NavbarSubmenuItem()->setLink('Identities:default'));
	});
	$menu = new NavbarMenu()->addMenuItem($item);

	$menu->resolveAclResources('PortalCustomer');

	$subItems = $item->getSubmenu()->getSubMenuItems();
	Assert::type(NavbarSubmenuHeading::class, $subItems[0]);
	Assert::same('portalCustomer.identities', $subItems[1]->getAclResource()->getResourceId());
});


test('resolveAclResources vraci menu kvuli retezeni', function () {
	$menu = new NavbarMenu();

	Assert::same($menu, $menu->resolveAclResources('PortalBackoffice'));
});


test('polozka podmenu ma vlastni vychozi hodnoty', function () {
	$item = new NavbarSubmenuItem();

	Assert::same('Test', $item->getLabel());
	Assert::same('chart-simple', $item->getFaIcon());
	Assert::same('#', $item->getLink());
	Assert::same([], $item->getLinkArgs());
	Assert::null($item->getAclResource());
});


test('polozka podmenu se chova stejne jako polozka menu', function () {
	$item = new NavbarSubmenuItem()
		->setLabel('Identity')
		->setFaIcon('users')
		->setLink('Identities:default')
		->setLinkArgs(['id' => 1])
		->setAclResource(new StringResource('portalBackoffice.identities'));

	Assert::same('Identity', $item->getLabel());
	Assert::same('users', $item->getFaIcon());
	Assert::same('Identities:default', $item->getLink());
	Assert::same(['id' => 1], $item->getLinkArgs());
	Assert::true($item->isCurrent(new TestComponent(['Identities:default'])));
	Assert::false($item->isVisible(new TestSecurityUser(), new TestComponent()));
	Assert::true($item->isVisible(new TestSecurityUser(['portalBackoffice.identities']), new TestComponent()));
});


test('nadpis v podmenu nese jen popisek', function () {
	Assert::same('Uzivatele', new NavbarSubmenuHeading('Uzivatele')->getLabel());
});


test('uzivatelske menu ma vychozi polozky zapnute', function () {
	$menu = new UserMenu();

	Assert::true($menu->isAddMyProfileMenuItem());
	Assert::true($menu->isFirebaseMenuItem());
	Assert::null($menu->getProfileLink());
	Assert::same([], $menu->getMenuItems());
});


test('uzivatelske menu se da nastavit', function () {
	$menu = new UserMenu();
	$item = new UserMenuItem()->setLabel('Odhlasit')->setFaIcon('sign-out')->setLink('Sign:out')->setLinkArgs(['a' => 1]);

	$menu->addMenuItem($item)->setAddMyProfileMenuItem(false)->setFirebaseMenuItem(false)->setProfileLink('Profile:default');

	Assert::same([$item], $menu->getMenuItems());
	Assert::false($menu->isAddMyProfileMenuItem());
	Assert::false($menu->isFirebaseMenuItem());
	Assert::same('Profile:default', $menu->getProfileLink());
	Assert::same('Odhlasit', $item->getLabel());
	Assert::same('sign-out', $item->getFaIcon());
	Assert::same('Sign:out', $item->getLink());
	Assert::same(['a' => 1], $item->getLinkArgs());
});


test('polozka uzivatelskeho menu pozna aktualni odkaz', function () {
	$item = new UserMenuItem()->setLink('Profile:default');

	Assert::true($item->isCurrent(new TestComponent(['Profile:default'])));
	Assert::false($item->isCurrent(new TestComponent()));
});
