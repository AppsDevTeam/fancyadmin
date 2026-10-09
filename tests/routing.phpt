<?php

declare(strict_types=1);

use ADT\FancyAdmin\Core\FancyAdminCustomerRouteList;
use ADT\FancyAdmin\Core\FancyAdminRouteList;
use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAccountQueryFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use ADT\Routing\RouteList;
use Tester\Assert;

/**
 * Routy administrace.
 *
 * Vsechny masky se predrazuji adresou administrace, aby administrace bezela na vlastnim
 * hostu (nebo podadresari) a nemichala se s verejnou casti projektu.
 */

require __DIR__ . '/bootstrap.php';


function createRouteList(string $module = 'Portal', array $config = []): FancyAdminRouteList
{
	return new FancyAdminRouteList(
		$module,
		FancyAdminFactory::create($config),
		new TestSecurityUser(),
		new TestEntityManager(),
		new TestAccountQueryFactory(),
	);
}

function createRouter(array $config = []): FancyAdminRouter
{
	return new FancyAdminRouter(
		FancyAdminFactory::create($config),
		new TestSecurityUser(),
		new TestEntityManager(),
		new TestAccountQueryFactory(),
	);
}

/** @return list<string> */
function masks(RouteList $routeList): array
{
	return array_map(fn($route) => $route->getMask(), $routeList->getRouters());
}


test('samotna domena se doplni o https', function () {
	Assert::same('https://admin.example.com', createRouteList()->getAdminHost());
});


test('cesta zacinajici lomitkem zustane cestou', function () {
	// Administrace na podadresari verejneho webu - schema se nedoplnuje.
	Assert::same('/admin', createRouteList(config: ['adminHostPath' => '/admin'])->getAdminHost());
	Assert::same('/sprava/admin', createRouteList(config: ['adminHostPath' => '/sprava/admin'])->getAdminHost());
});


test('domena s cestou se taky doplni o https', function () {
	Assert::same('https://example.com/admin', createRouteList(config: ['adminHostPath' => 'example.com/admin'])->getAdminHost());
});


test('maska routy se predradi adresou administrace', function () {
	$routeList = createRouteList();

	$routeList->addRoute('sign/in', ['presenter' => 'Sign', 'action' => 'in']);

	Assert::same(['https://admin.example.com/sign/in'], masks($routeList));
});


test('zakaznicka cast ma v masce vybrany ucet', function () {
	$routeList = new FancyAdminCustomerRouteList(
		'PortalCustomer',
		FancyAdminFactory::create(),
		new TestSecurityUser(),
		new TestEntityManager(),
		new TestAccountQueryFactory(),
	);

	$routeList->addRoute('<presenter>[/<action>]', ['presenter' => 'Home', 'action' => 'default']);

	Assert::same(['https://admin.example.com/<selectedAccount \d+>/<presenter>[/<action>]'], masks($routeList));
});


test('routy prihlaseni', function () {
	$masks = masks(createRouter()->getPortalRouteList());

	Assert::same([
		'https://admin.example.com/sign/in',
		'https://admin.example.com/sign/out',
		'https://admin.example.com/sign/two-factor',
		'https://admin.example.com/sign/new-password',
		'https://admin.example.com/sign/password-set',
		'https://admin.example.com/sign/lost-password',
	], $masks);
});


test('vypnuta obnova hesla routu nepridava', function () {
	// Bez toho by stranka pro obnovu hesla zustala dostupna, i kdyz je funkce vypnuta.
	$masks = masks(createRouter(['lostPasswordEnabled' => false])->getPortalRouteList());

	Assert::notContains('https://admin.example.com/sign/lost-password', $masks);
	Assert::count(5, $masks);
});


test('vypnuty Keycloak zadne SSO routy nepridava', function () {
	$router = createRouter();

	Assert::null($router->getKeycloakRouteList());
	Assert::same([], array_filter(masks($router->getPortalRouteList()), fn($mask) => str_contains($mask, 'keycloak')));
});


test('zapnuty Keycloak prida routy pro prihlaseni i logovani', function () {
	$router = createRouter(['keycloakEnabled' => true]);

	$masks = masks($router->getPortalRouteList());
	Assert::contains('https://admin.example.com/keycloak-auth/<action>', $masks);
	Assert::contains('https://admin.example.com/keycloak-log/<action>', $masks);
});


test('samostatny Keycloak route list se registruje pred catch-all routami', function () {
	$router = createRouter(['keycloakEnabled' => true]);

	$keycloakRoutes = $router->getKeycloakRouteList();

	Assert::type(RouteList::class, $keycloakRoutes);
	Assert::same([
		'https://admin.example.com/keycloak-auth/<action>',
		'https://admin.example.com/keycloak-log/<action>',
	], masks($keycloakRoutes));
	Assert::same('Portal:', $keycloakRoutes->getModule());
});


test('zakaznicke a backoffice routy maji vlastni moduly', function () {
	$router = createRouter();

	Assert::same('PortalCustomer:', $router->getCustomerRouteList()->getModule());
	Assert::same('PortalBackoffice:', $router->getBackofficeRouteList()->getModule());
	Assert::same('Portal:', $router->getPortalRouteList()->getModule());
});


test('kazda cast ma detailni i obecnou routu', function () {
	$router = createRouter();

	Assert::same([
		'https://admin.example.com/<selectedAccount \d+>/<presenter>/<id \d+>',
		'https://admin.example.com/<selectedAccount \d+>/<presenter>[/<id \d+>][/<action>]',
	], masks($router->getCustomerRouteList()));

	Assert::same([
		'https://admin.example.com/<presenter>/<id \d+>',
		'https://admin.example.com/<presenter>[/<id \d+>][/<action>]',
	], masks($router->getBackofficeRouteList()));
});


test('route listy se skladaji jen jednou', function () {
	$router = createRouter();

	Assert::same($router->getPortalRouteList(), $router->getPortalRouteList());
	Assert::same($router->getCustomerRouteList(), $router->getCustomerRouteList());
	Assert::same($router->getBackofficeRouteList(), $router->getBackofficeRouteList());
	Assert::same($router->getRouteList(), $router->getRouteList());
});


test('slozeny route list ma poradi portal, zakaznik, backoffice', function () {
	// Poradi rozhoduje: obecne catch-all routy zakaznicke a backoffice casti by jinak
	// prebraly i prihlasovaci adresy.
	$router = createRouter();

	$routeList = $router->getRouteList();

	Assert::same([
		$router->getPortalRouteList(),
		$router->getCustomerRouteList(),
		$router->getBackofficeRouteList(),
	], $routeList->getRouters());
});


test('filtr podle query objektu prevede entitu na id a zpet', function () {
	$router = createRouter();
	$entity = new ADT\FancyAdmin\Tests\Fixtures\TestAccount('Firma')->setId(42);

	$query = new class($entity) implements ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery {
		public bool $notFound = false;
		public mixed $askedId = null;

		public function __construct(private readonly object $entity)
		{
		}

		public function byId($id): static
		{
			$this->askedId = $id;
			return $this;
		}

		public function fetchOneOrNull(bool $strict = true): ?object
		{
			return $this->notFound ? null : $this->entity;
		}

		public function by(array|string $column, mixed $value, ADT\DoctrineComponents\QueryObject\QueryObjectByMode $mode = ADT\DoctrineComponents\QueryObject\QueryObjectByMode::AUTO): static
		{
			return $this;
		}

		public function orderBy(array|string $field, ?string $order = null): static
		{
			return $this;
		}

		public function fetch(?int $limit = null): array
		{
			return [];
		}

		public function fetchIterable(): Generator
		{
			yield from [];
		}

		public function fetchOne(bool $strict = true): object
		{
			return $this->entity;
		}

		public function fetchPairs(?string $value = 'name', ?string $key = 'id'): array
		{
			return [];
		}

		public function fetchField(string $field): array
		{
			return [];
		}

		public function init(): void
		{
		}

		public function setSecurityUser(ADT\FancyAdmin\Model\Security\SecurityUser $securityUser): static
		{
			return $this;
		}

		public function disableSecurityFilter(): static
		{
			return $this;
		}

		public function disableAccountFilter(): static
		{
			return $this;
		}

		public function byIdNot(int|array $id): static
		{
			return $this;
		}
	};

	$filter = $router->createFilterByQueryObject($query);

	Assert::same($entity, $filter[Nette\Routing\Route::FilterIn]('42'));
	Assert::same('42', $query->askedId);
	Assert::same(42, $filter[Nette\Routing\Route::FilterOut]($entity));
});


test('neexistujici entita v URL konci chybou 404', function () {
	$router = createRouter();
	$query = new class implements ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery {
		public function byId($id): static
		{
			return $this;
		}

		public function fetchOneOrNull(bool $strict = true): ?object
		{
			return null;
		}

		public function by(array|string $column, mixed $value, ADT\DoctrineComponents\QueryObject\QueryObjectByMode $mode = ADT\DoctrineComponents\QueryObject\QueryObjectByMode::AUTO): static
		{
			return $this;
		}

		public function orderBy(array|string $field, ?string $order = null): static
		{
			return $this;
		}

		public function fetch(?int $limit = null): array
		{
			return [];
		}

		public function fetchIterable(): Generator
		{
			yield from [];
		}

		public function fetchOne(bool $strict = true): object
		{
			throw new LogicException();
		}

		public function fetchPairs(?string $value = 'name', ?string $key = 'id'): array
		{
			return [];
		}

		public function fetchField(string $field): array
		{
			return [];
		}

		public function init(): void
		{
		}

		public function setSecurityUser(ADT\FancyAdmin\Model\Security\SecurityUser $securityUser): static
		{
			return $this;
		}

		public function disableSecurityFilter(): static
		{
			return $this;
		}

		public function disableAccountFilter(): static
		{
			return $this;
		}

		public function byIdNot(int|array $id): static
		{
			return $this;
		}
	};

	$filter = $router->createFilterByQueryObject($query);

	Assert::exception(
		fn() => $filter[Nette\Routing\Route::FilterIn]('999'),
		Nette\Application\BadRequestException::class,
	);

	try {
		$filter[Nette\Routing\Route::FilterIn]('999');
	} catch (Nette\Application\BadRequestException $e) {
		Assert::same(Nette\Http\IResponse::S404_NotFound, $e->getHttpCode());
	}
});
