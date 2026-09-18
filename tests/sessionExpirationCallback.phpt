<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\SessionExpirationCallback;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestAclResource;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestConfiguration;
use ADT\FancyAdmin\Tests\Fixtures\TestConfigurationQueryFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestForeignIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use Nette\Utils\Json;
use Tester\Assert;

/**
 * Expirace session podle politiky hesel.
 *
 * Politika je ulozena v konfiguraci pod klicem podle toho, kam uzivatel patri:
 * admin ma vlastni, ostatni s pristupem do backoffice svou. Kdo do backoffice nepatri,
 * zadnou vlastni expiraci nedostane.
 */

require __DIR__ . '/bootstrap.php';


function policy(array $values): TestConfiguration
{
	return new TestConfiguration()->setValue(Json::encode($values));
}

function createCallback(array $configurations): SessionExpirationCallback
{
	return new SessionExpirationCallback(
		new TestConfigurationQueryFactory($configurations),
		FancyAdminFactory::create(),
	);
}

function adminIdentity(): TestIdentity
{
	return new TestIdentity()->addRole(new TestAclRole('admin', isAdmin: true));
}

function backofficeIdentity(): TestIdentity
{
	return new TestIdentity()->addRole(
		new TestAclRole('editor')->allowResource(new TestAclResource('portalBackoffice.dashboard')),
	);
}


test('cizi identita se neresi', function () {
	$callback = createCallback([]);

	Assert::null($callback(new TestForeignIdentity()));
});


test('uzivatel mimo backoffice zadnou vlastni expiraci nedostane', function () {
	$callback = createCallback(['password.policy.backoffice' => policy(['enabled' => true, 'sessionExpirationMinutes' => 30])]);

	Assert::null($callback(new TestIdentity()));
	Assert::null($callback(new TestIdentity()->addRole(new TestAclRole('customer'))));
});


test('admin dostane expiraci z admin politiky', function () {
	$callback = createCallback(['password.policy.admin' => policy(['enabled' => true, 'sessionExpirationMinutes' => 15])]);

	Assert::same('15 minutes', $callback(adminIdentity()));
});


test('uzivatel backoffice dostane expiraci z backoffice politiky', function () {
	$callback = createCallback(['password.policy.backoffice' => policy(['enabled' => true, 'sessionExpirationMinutes' => 60])]);

	Assert::same('60 minutes', $callback(backofficeIdentity()));
});


test('admin politika ma prednost pred backoffice politikou', function () {
	$callback = createCallback([
		'password.policy.admin' => policy(['enabled' => true, 'sessionExpirationMinutes' => 15]),
		'password.policy.backoffice' => policy(['enabled' => true, 'sessionExpirationMinutes' => 60]),
	]);

	Assert::same('15 minutes', $callback(adminIdentity()));
});


test('chybejici konfigurace necha vychozi expiraci', function () {
	Assert::null(createCallback([])(adminIdentity()));
});


test('vypnuta politika expiraci nemeni', function () {
	$callback = createCallback(['password.policy.admin' => policy(['enabled' => false, 'sessionExpirationMinutes' => 15])]);

	Assert::null($callback(adminIdentity()));

	// Chybejici priznak se bere jako vypnuto.
	Assert::null(createCallback(['password.policy.admin' => policy(['sessionExpirationMinutes' => 15])])(adminIdentity()));
});


test('nesmyslny nebo chybejici pocet minut se ignoruje', function () {
	foreach ([null, 0, -5] as $minutes) {
		$callback = createCallback(['password.policy.admin' => policy(['enabled' => true, 'sessionExpirationMinutes' => $minutes])]);
		Assert::null($callback(adminIdentity()), var_export($minutes, true));
	}

	Assert::null(createCallback(['password.policy.admin' => policy(['enabled' => true])])(adminIdentity()));
});


test('konfigurace se cte bez ohledu na prihlaseneho uzivatele a jeho ucet', function () {
	// Callback bezi pri obnove session, kdy jeste neni koho filtrovat - oba filtry
	// se proto musi vypnout, jinak by se politika nenasla.
	$factory = new TestConfigurationQueryFactory(['password.policy.admin' => policy(['enabled' => true, 'sessionExpirationMinutes' => 15])]);

	new SessionExpirationCallback($factory, FancyAdminFactory::create())(adminIdentity());

	Assert::true($factory->lastQuery->securityFilterDisabled);
	Assert::true($factory->lastQuery->accountFilterDisabled);
	Assert::same('password.policy.admin', $factory->lastQuery->askedKey);
});
