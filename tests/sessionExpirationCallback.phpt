<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\SessionExpirationCallback;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestForeignIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use Tester\Assert;

/**
 * Expirace session podle politiky hesel.
 *
 * Politika drive visela na dvou pevnych radcich ciselniku `configuration` (admin
 * a backoffice), ted ji nese role - viz PasswordPolicy. Callback uz tedy nic nedohledava,
 * jen z roli identity vezme nejprisnejsi politiku a prelozi minuty na retezec pro Nette.
 *
 * Slucovani politik z vic roli ma vlastni testy v passwordPolicy.phpt, tady jde o to,
 * co z nej callback udela.
 */

require __DIR__ . '/bootstrap.php';


function roleWithExpiration(?int $minutes, bool $enabled = true, string $name = 'role'): TestAclRole
{
	return new TestAclRole($name)
		->setPasswordPolicyEnabled($enabled)
		->setSessionExpirationMinutes($minutes);
}

function identityWithRoles(TestAclRole ...$roles): TestIdentity
{
	$identity = new TestIdentity();

	foreach ($roles as $_role) {
		$identity->addRole($_role);
	}

	return $identity;
}


test('cizi identita se neresi', function () {
	Assert::null(new SessionExpirationCallback()(new TestForeignIdentity()));
});


test('identita bez roli zadnou vlastni expiraci nedostane', function () {
	Assert::null(new SessionExpirationCallback()(new TestIdentity()));
});


test('role s politikou urcuje expiraci', function () {
	Assert::same('15 minutes', new SessionExpirationCallback()(identityWithRoles(roleWithExpiration(15))));
});


test('z vic roli plati nejkratsi expirace', function () {
	// Nejprisnejsi cteni - jedna volna role nesmi prodlouzit session dana prisnou roli.
	$identity = identityWithRoles(
		roleWithExpiration(60, name: 'editor'),
		roleWithExpiration(15, name: 'admin'),
	);

	Assert::same('15 minutes', new SessionExpirationCallback()($identity));
});


test('vypnuta politika expiraci nemeni', function () {
	Assert::null(new SessionExpirationCallback()(identityWithRoles(roleWithExpiration(15, enabled: false))));
});


test('nesmyslny nebo nevyplneny pocet minut se ignoruje', function () {
	foreach ([null, 0, -5] as $minutes) {
		Assert::null(
			new SessionExpirationCallback()(identityWithRoles(roleWithExpiration($minutes))),
			var_export($minutes, true),
		);
	}
});


test('role bez expirace nezrusi expiraci z jine role', function () {
	$identity = identityWithRoles(
		roleWithExpiration(null, name: 'editor'),
		roleWithExpiration(30, name: 'admin'),
	);

	Assert::same('30 minutes', new SessionExpirationCallback()($identity));
});
