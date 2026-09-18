<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakAdminAccessToken;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakAuthentication;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakUser;
use Tester\Assert;

/**
 * Hodnotove objekty kolem Keycloaku.
 */

require __DIR__ . '/bootstrap.php';


test('uzivatel z Keycloaku nese identifikatory a jmeno', function () {
	$user = new KeycloakUser('uuid-1', 'jnovak', 'Jan', 'Novak', 'jan@example.com');

	Assert::same('uuid-1', $user->getId());
	Assert::same('jnovak', $user->getUsername());
	Assert::same('Jan', $user->getFirstName());
	Assert::same('Novak', $user->getLastName());
	Assert::same('jan@example.com', $user->getEmail());
});


test('jmeno i e-mail uzivatele jsou nepovinne', function () {
	$user = new KeycloakUser('uuid-1', 'jnovak', null, null, null);

	Assert::null($user->getFirstName());
	Assert::null($user->getLastName());
	Assert::null($user->getEmail());
});


test('admin token plati, dokud nevyprsi', function () {
	$token = new KeycloakAdminAccessToken('abc', 60);

	Assert::same('abc', $token->getToken());
	Assert::true($token->isValid());
});


test('platnost admin tokenu se krati o rezervu', function () {
	// Od expirace se odecita 5 sekund, aby se token stihl pregenerovat driv, nez ho
	// Keycloak odmitne. Token s platnosti 5 s je proto rovnou neplatny.
	Assert::false(new KeycloakAdminAccessToken('abc', 5)->isValid());
	Assert::false(new KeycloakAdminAccessToken('abc', 0)->isValid());
	Assert::true(new KeycloakAdminAccessToken('abc', 6)->isValid());
});


test('prosly admin token neplati', function () {
	Assert::false(new KeycloakAdminAccessToken('abc', -60)->isValid());
});


test('autentizace nese oba tokeny i udaje o uzivateli', function () {
	$userInfo = ['sub' => 'uuid-1', 'email' => 'jan@example.com'];
	$auth = new KeycloakAuthentication('access', 'refresh', 300, 1800, $userInfo, 'id-token');

	Assert::same('access', $auth->getAccessToken());
	Assert::same('refresh', $auth->getRefreshToken());
	Assert::same($userInfo, $auth->getUserInfo());
	Assert::same('id-token', $auth->getIdToken());
});


test('expirace se prepocitava na cas', function () {
	$auth = new KeycloakAuthentication('access', 'refresh', 300, 1800, []);

	$expiresIn = $auth->getExpiresIn();
	$refreshExpiresIn = $auth->getRefreshExpiresIn();

	Assert::type(DateTime::class, $expiresIn);
	// Presnost na sekundy staci; test nesmi spadnout na prechodu pres sekundu.
	Assert::true(abs($expiresIn->getTimestamp() - (time() + 300)) <= 1);
	Assert::true(abs($refreshExpiresIn->getTimestamp() - (time() + 1800)) <= 1);
});


test('id token je nepovinny', function () {
	Assert::null(new KeycloakAuthentication('access', 'refresh', 300, 1800, [])->getIdToken());
});


test('prevod do pole pro ulozeni do session', function () {
	$auth = new KeycloakAuthentication('access', 'refresh', 300, 1800, [], 'id-token');

	$array = $auth->toArray();

	Assert::same(['access_token', 'refresh_token', 'expires_in', 'refresh_expires_in', 'id_token'], array_keys($array));
	Assert::same('access', $array['access_token']);
	Assert::same('refresh', $array['refresh_token']);
	Assert::same('id-token', $array['id_token']);
	Assert::match('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $array['expires_in']);
	Assert::match('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $array['refresh_expires_in']);
});


test('POZOR: chybejici refresh token getter shodi', function () {
	// Vlastnost je ?string, ale getter slibuje string - Keycloak refresh token u nekterych
	// toku (napr. prompt=none bez offline_access) neposila.
	$auth = new KeycloakAuthentication('access', null, 300, 1800, []);

	Assert::exception($auth->getRefreshToken(...), TypeError::class);
	// Prevod do pole s null projde, protoze cte primo vlastnost.
	Assert::null($auth->toArray()['refresh_token']);
});


test('nazvy klicu v session se nesmi zmenit', function () {
	// Podle nich se cisti session pri odhlaseni - preklep by nechal data v session lezet.
	Assert::same('keycloak', KeycloakSessionSection::SECTION_NAME);
	Assert::same('keycloak', new KeycloakSessionSection()->getSectionName());

	Assert::same([
		'idToken',
		'nonExistingSsoLogin',
		'logoutUrl',
		'authAttemptCount',
		'authAttemptLastTime',
		'ssoInstanceName',
		'ssoSilentTried',
		'ssoSuppressSilent',
		'authStates',
	], new KeycloakSessionSection()->getSessionKeys());
});


test('nazvy klicu passkey session se nesmi zmenit', function () {
	Assert::same('passkey', ADT\FancyAdmin\Model\Security\Passkey\PasskeySessionSection::SECTION_NAME);
	Assert::same('passkey', new ADT\FancyAdmin\Model\Security\Passkey\PasskeySessionSection()->getSectionName());
	Assert::same('5 minutes', ADT\FancyAdmin\Model\Security\Passkey\PasskeySessionSection::CHALLENGE_EXPIRATION);
	Assert::same(
		['createChallenge', 'getChallenge', 'passkeySession'],
		new ADT\FancyAdmin\Model\Security\Passkey\PasskeySessionSection()->getSessionKeys(),
	);
});
