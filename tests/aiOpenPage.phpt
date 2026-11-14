<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\AiChat\Page\AiPage;
use ADT\FancyAdmin\Model\AiChat\Page\AiPageTarget;
use ADT\FancyAdmin\Model\AiChat\Page\OpenPageTool;
use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenu;
use ADT\FancyAdmin\Model\Menu\NavbarSubmenuItem;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * Nastroj AI open_page: stranky z menu (bez presenteru, prava jako v menu) a stranky
 * zaznamu projektu (AiPage), vysledek {type: navigate} s cestou na vlastnim webu.
 */

require __DIR__ . '/bootstrap.php';


final class OpenPageTestMenuFactory implements NavbarMenuFactory
{
	/** Co dostala podminka polozky misto presenteru */
	public static array $conditionPresenters = [];

	public function create(): NavbarMenu
	{
		return (new NavbarMenu())
			->addMenuItem((new NavbarMenuItem())->setLabel('Úkoly')->setLink('Task:default'))
			->addMenuItem(
				(new NavbarMenuItem())
					->setLabel('Poptávky')
					->setLink('Inquiry:default')
					->setCondition(static function ($user, $presenter): bool {
						self::$conditionPresenters[] = $presenter;
						return false;
					}),
			)
			->addMenuItem(
				(new NavbarMenuItem())
					->setLabel('Pokladna')
					->setupSubmenuItems(static function (NavbarSubmenu $submenu): void {
						$submenu->addMenuItem((new NavbarSubmenuItem())->setLabel('Pokladní kniha')->setLink('CashBook:default'));
					}),
			);
	}
}


function clientPage(): AiPage
{
	return new class implements AiPage {
		public function getName(): string
		{
			return 'client';
		}

		public function getDescription(): string
		{
			return 'card of one client; id = client.id';
		}

		public function resolve(int $id): ?AiPageTarget
		{
			return $id === 12 ? new AiPageTarget('Client:detail', ['id' => 12], '[CLIENT #12]') : null;
		}
	};
}


function openPageTool(TestSecurityUser $user, ?string $menuFactory = OpenPageTestMenuFactory::class): OpenPageTool
{
	return new OpenPageTool(
		[clientPage()],
		new LinkGenerator(new SimpleRouter(), new UrlScript('https://example.test/')),
		$user,
		'PortalBackoffice',
		$menuFactory,
	);
}


test('definice vyjmenuje stranky menu i zaznamu', function () {
	$definition = openPageTool(new TestSecurityUser())->getDefinition();

	Assert::same('open_page', $definition['name']);
	Assert::same(['Task:default', 'Inquiry:default', 'CashBook:default', 'client'], $definition['input_schema']['properties']['page']['enum']);
	Assert::contains('- `CashBook:default`: Pokladna › Pokladní kniha', $definition['description']);
	Assert::contains('- `client` (needs id): card of one client', $definition['description']);
});


test('stranka z menu podle prav menu (resource odvozeny z odkazu)', function () {
	$denied = openPageTool(new TestSecurityUser())->handle(['page' => 'Task:default']);
	Assert::same('The user is not allowed to open this page.', $denied['error']);

	$result = openPageTool(new TestSecurityUser(['portalBackoffice.task']))->handle(['page' => 'Task:default']);
	Assert::same('navigate', $result['type']);
	Assert::same('Úkoly', $result['title']);
	Assert::same('/?action=default&presenter=PortalBackoffice%3ATask', $result['url']);
});


test('podminka polozky dostane misto presenteru null', function () {
	OpenPageTestMenuFactory::$conditionPresenters = [];

	$result = openPageTool(new TestSecurityUser(['portalBackoffice.inquiry']))->handle(['page' => 'Inquiry:default']);

	Assert::same('The user is not allowed to open this page.', $result['error']);
	Assert::same([null], OpenPageTestMenuFactory::$conditionPresenters);
});


test('polozka podmenu s popiskem rodice', function () {
	$result = openPageTool(new TestSecurityUser(['portalBackoffice.cashBook']))->handle(['page' => 'CashBook:default']);

	Assert::same('Pokladna › Pokladní kniha', $result['title']);
	Assert::same('/?action=default&presenter=PortalBackoffice%3ACashBook', $result['url']);
});


test('stranka zaznamu: id, existence a pravo na presenter', function () {
	$user = new TestSecurityUser(['portalBackoffice.client']);

	Assert::same('Page "client" needs the id of the record.', openPageTool($user)->handle(['page' => 'client'])['error']);
	Assert::same('Record 5 does not exist or the user may not see it.', openPageTool($user)->handle(['page' => 'client', 'id' => 5])['error']);
	Assert::same('The user is not allowed to open this page.', openPageTool(new TestSecurityUser())->handle(['page' => 'client', 'id' => 12])['error']);

	$result = openPageTool($user)->handle(['page' => 'client', 'id' => '12']);
	Assert::same(['type' => 'navigate', 'url' => '/?id=12&action=detail&presenter=PortalBackoffice%3AClient', 'title' => '[CLIENT #12]', 'opened' => true], $result);
});


test('neznama stranka, neprihlaseny uzivatel, bez menu', function () {
	Assert::same('Unknown page "Nope:default".', openPageTool(new TestSecurityUser(isAdmin: true))->handle(['page' => 'Nope:default'])['error']);
	Assert::same('The user is not allowed to open this page.', openPageTool(new TestSecurityUser(isAdmin: true, isLoggedIn: false))->handle(['page' => 'Task:default'])['error']);
	Assert::same(['client'], openPageTool(new TestSecurityUser(), null)->getDefinition()['input_schema']['properties']['page']['enum']);
});
