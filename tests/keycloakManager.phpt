<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\KeycloakFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestRepository;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use ADT\FancyAdmin\Tests\Fixtures\TestSession;
use ADT\FancyAdmin\Tests\Fixtures\TestSso;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;
use Tracy\Debugger;

/**
 * KeycloakManager - lazy skladani SSO instanci z databaze.
 *
 * Nejdulezitejsi je, kdy se instance NEVRATI: neaktivni se vynechava jen pri prihlasovani
 * (getInstanceForIdentity), ne pri dohledani podle nazvu - jinak by deaktivace uveznila
 * uz prihlasene uzivatele. A instance s verejnou URL mimo allowlist se nepouzije vubec.
 */

require __DIR__ . '/bootstrap.php';


/**
 * @param list<TestSso> $ssoRecords
 * @return array{0: KeycloakManager, 1: TestSession, 2: TestRepository}
 */
function createManager(array $ssoRecords = [], array $allowedHosts = ['auth.example.com']): array
{
	$em = new TestEntityManager([TestSso::class]);
	/** @var TestRepository $repository */
	$repository = $em->getRepository(TestSso::class);
	$repository->entities = $ssoRecords;

	$session = new TestSession();
	$manager = new ReflectionClass(KeycloakManager::class)->newInstanceWithoutConstructor();

	foreach ([
		'em' => $em,
		'linkGenerator' => KeycloakFactory::createLinkGenerator(),
		'securityUser' => new TestSecurityUser(),
		'identityQueryFactory' => new class implements ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory {
			public function create(): ADT\DoctrineAuthenticator\OTP\IdentityQuery
			{
				throw new LogicException('Nepouziva se.');
			}
		},
		'aclRoleQueryFactory' => new class implements ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory {
			public function create(): ADT\FancyAdmin\Model\Queries\AclRoleQuery
			{
				throw new LogicException('Nepouziva se.');
			}
		},
		'fancyAdmin' => FancyAdminFactory::create(['keycloakEnabled' => true, 'ssoAllowedHosts' => $allowedHosts]),
		'session' => $session,
		'storage' => new MemoryStorage(),
		'verifySsl' => true,
	] as $property => $value) {
		new ReflectionProperty(KeycloakManager::class, $property)->setValue($manager, $value);
	}

	return [$manager, $session, $repository];
}

function createLogDir(string $name): string
{
	$dir = sys_get_temp_dir() . '/fancyadmin-tests/' . getmypid() . '/' . $name;
	@mkdir($dir, 0777, recursive: true);
	Tester\Helpers::purge($dir);
	Debugger::$logDirectory = $dir;

	return $dir;
}

function requiresSso(KeycloakManager $manager, TestIdentity $identity): bool
{
	return new ReflectionMethod($manager, 'identityRequiresSso')->invoke($manager, $identity);
}


test('instance se slozi z databazoveho zaznamu', function () {
	[$manager] = createManager([new TestSso('produkce')]);

	$instance = $manager->getInstance('produkce');

	Assert::type(Keycloak::class, $instance);
	Assert::same('produkce', $instance->getInstanceName());
	Assert::same('test-realm', $instance->getRealm());
	Assert::same('https://auth.example.com', $instance->getHostUrl());
	Assert::same('client', $instance->getClientId());
	Assert::same('frontend', $instance->getFrontendClientId());
});


test('instance se skladaji jen jednou', function () {
	[$manager] = createManager([new TestSso('produkce')]);

	Assert::same($manager->getInstance('produkce'), $manager->getInstance('produkce'));
});


test('neznama instance neexistuje', function () {
	[$manager] = createManager([new TestSso('produkce')]);

	Assert::null($manager->getInstance('neexistuje'));
});


test('neaktivni instance se podle nazvu porad najde', function () {
	// Podle nazvu se dohledava i pro callback rozpracovaneho requestu a odhlaseni -
	// kdyby filtrovala i tady, deaktivace by uveznila uz prihlasene uzivatele.
	[$manager] = createManager([new TestSso('produkce', isActive: false)]);

	Assert::type(Keycloak::class, $manager->getInstance('produkce'));
});


test('instance s verejnou URL mimo allowlist se nepouzije', function () {
	$logDir = createLogDir('allowlist');

	[$manager] = createManager([new TestSso('produkce', 'https://collaborator.example.net')]);

	Assert::null($manager->getInstance('produkce'));
	// Odmitnuti se loguje jako kriticke - jinak by admin nepoznal, proc SSO nefunguje.
	Assert::contains('mimo allowlist', file_get_contents($logDir . '/critical.log'));
});


test('prazdny allowlist nepousti zadnou instanci', function () {
	createLogDir('prazdny-allowlist');

	[$manager] = createManager([new TestSso('produkce')], allowedHosts: []);

	Assert::null($manager->getInstance('produkce'));
});


test('instance podle SSO zaznamu', function () {
	$sso = new TestSso('produkce');
	[$manager] = createManager([$sso]);

	Assert::same($manager->getInstance('produkce'), $manager->getInstanceForSso($sso));
});


test('SSO vyzaduje vazbu i roli s priznakem', function () {
	[$manager] = createManager([new TestSso('produkce')]);
	$identity = new TestIdentity();

	// Bez vazby na SSO.
	Assert::false(requiresSso($manager, $identity));

	// Vazba je, ale zadna role SSO nevyzaduje.
	$identity->setSso(new TestSso('produkce'))->addRole(new TestAclRole('editor'));
	Assert::false(requiresSso($manager, $identity));

	$identity->addRole(new TestAclRole('sso-role')->setNeedsSso(true));
	Assert::true(requiresSso($manager, $identity));
});


test('role s needsSso bez vazby na instanci nestaci', function () {
	[$manager] = createManager([new TestSso('produkce')]);
	$identity = new TestIdentity()->addRole(new TestAclRole('sso-role')->setNeedsSso(true));

	Assert::false(requiresSso($manager, $identity));
	Assert::null($manager->getInstanceForIdentity($identity));
});


test('identita s SSO dostane svou instanci', function () {
	$sso = new TestSso('produkce');
	[$manager] = createManager([$sso]);
	$identity = new TestIdentity();
	$identity->setSso($sso)->addRole(new TestAclRole('sso-role')->setNeedsSso(true));

	Assert::same($manager->getInstance('produkce'), $manager->getInstanceForIdentity($identity));
});


test('neaktivni instance se pri prihlasovani vynechava', function () {
	// Nouzovy rezim: uzivatel se pak chova jako bezny uzivatel s heslem.
	$sso = new TestSso('produkce', isActive: false);
	[$manager] = createManager([$sso]);
	$identity = new TestIdentity();
	$identity->setSso($sso)->addRole(new TestAclRole('sso-role')->setNeedsSso(true));

	Assert::null($manager->getInstanceForIdentity($identity));
	// Pro ukonceni neceho, co uz bezi (odhlaseni), se instance vrati i tak.
	Assert::type(Keycloak::class, $manager->getInstanceForIdentity($identity, activeOnly: false));
});


test('instance z prihlaseni se drzi v session', function () {
	[$manager, $session] = createManager([new TestSso('produkce')]);

	Assert::null($manager->getInstanceFromSession());

	$manager->storeInstanceInSession('produkce');

	Assert::same('produkce', $session->getSection(KeycloakSessionSection::SECTION_NAME)->get(KeycloakSessionSection::SSO_INSTANCE_NAME));
	Assert::same($manager->getInstance('produkce'), $manager->getInstanceFromSession());
});


test('neznama instance v session nic nevrati', function () {
	[$manager] = createManager([new TestSso('produkce')]);

	$manager->storeInstanceInSession('smazana');

	Assert::null($manager->getInstanceFromSession());
});


test('do prihlasovani vstupuji jen aktivni instance', function () {
	// Silent SSO na prihlasovaci strance prochazi tenhle seznam, takze jeden vadny zaznam
	// by presmeroval login cele platformy.
	[$manager, , $repository] = createManager([
		new TestSso('produkce'),
		new TestSso('archiv', isActive: false),
	]);

	Assert::true($manager->hasInstances());
	Assert::same(['produkce'], $manager->getInstanceNames());
	Assert::same([['isActive' => true]], array_slice($repository->findByCalls, 0, 1));
});


test('bez aktivnich instanci se SSO nenabizi', function () {
	[$manager] = createManager([new TestSso('archiv', isActive: false)]);

	Assert::false($manager->hasInstances());
	Assert::same([], $manager->getInstanceNames());
});


test('bez SSO zaznamu se SSO nenabizi', function () {
	[$manager] = createManager();

	Assert::false($manager->hasInstances());
	Assert::same([], $manager->getInstanceNames());
	Assert::null($manager->getInstance('cokoliv'));
});
