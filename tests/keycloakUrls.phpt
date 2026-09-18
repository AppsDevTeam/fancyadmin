<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\Tests\Fixtures\KeycloakFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestSession;
use Nette\Http\Url;
use Tester\Assert;

/**
 * URL, kterymi balicek posila prohlizec na Keycloak.
 *
 * Authorization Code Flow s PKCE: navratova URL se do Keycloaku neposila (drzi se
 * v session u state), redirect_uri je staticke, aby se dalo v Keycloaku vyjmenovat
 * bez wildcardu.
 */

require __DIR__ . '/bootstrap.php';


function query(string $url): array
{
	return new Url($url)->getQueryParameters();
}

function section(TestSession $session)
{
	return $session->getSection(KeycloakSessionSection::SECTION_NAME);
}


test('navratove adresy jsou staticke a nesou nazev instance', function () {
	$keycloak = KeycloakFactory::create(new TestSession(), 'produkce');

	Assert::same('https://admin.example.com/keycloak-auth/callback/produkce', $keycloak->getAuthRedirectUri());
	Assert::same('https://admin.example.com/keycloak-auth/silent-check/produkce', $keycloak->getSilentRedirectUri());
	Assert::same('https://admin.example.com/keycloak-auth/backchannel-logout/produkce', $keycloak->getBackchannelLogoutUrl());
});


test('tichou kontrolu lze nasmerovat na jinou akci', function () {
	$keycloak = KeycloakFactory::create(new TestSession(), 'produkce');

	Assert::same('https://admin.example.com/keycloak-auth/out/produkce', $keycloak->getSilentRedirectUri('out'));
});


test('nazev instance se propise do navratovych adres', function () {
	$keycloak = KeycloakFactory::create(new TestSession());

	Assert::same('default', $keycloak->getInstanceName());
	Assert::contains('/default', $keycloak->getAuthRedirectUri());

	$keycloak->setInstanceName('jina');
	Assert::same('jina', $keycloak->getInstanceName());
	Assert::contains('/jina', $keycloak->getAuthRedirectUri());
});


test('konfigurace instance je pristupna pro sablony', function () {
	$keycloak = KeycloakFactory::create(new TestSession());

	Assert::same(KeycloakFactory::HOST_URL, $keycloak->getHostUrl());
	Assert::same(KeycloakFactory::REALM, $keycloak->getRealm());
	Assert::same(KeycloakFactory::CLIENT_ID, $keycloak->getClientId());
	Assert::same('frontend-client', $keycloak->getFrontendClientId());
});


test('prihlasovaci URL nese PKCE a miri na realm instance', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session, 'produkce');

	$url = $keycloak->getLoginUrl();

	Assert::same('https://auth.example.com/realms/test-realm/protocol/openid-connect/auth', explode('?', $url)[0]);

	$query = query($url);
	Assert::same('admin-client', $query['client_id']);
	Assert::same('code', $query['response_type']);
	Assert::same('openid email profile', $query['scope']);
	Assert::same('S256', $query['code_challenge_method']);
	Assert::same('https://admin.example.com/keycloak-auth/callback/produkce', $query['redirect_uri']);
	Assert::match('#^[0-9a-f]{32}$#', $query['state']);
	Assert::match('#^[A-Za-z0-9_-]+$#', $query['code_challenge']);
});


test('navratova URL se do Keycloaku neposila', function () {
	// Kdyby sla v URL, dal by se z prihlasovaci stranky udelat odrazovy mustek.
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	$url = $keycloak->getLoginUrl('https://evil.example.net/phishing');

	Assert::notContains('evil.example.net', $url);
	Assert::notContains('phishing', rawurldecode($url));

	$state = query($url)['state'];
	Assert::same('https://evil.example.net/phishing', $keycloak->consumeAuthState($state)['backRedirect']);
});


test('prihlasovaci URL umi predvyplnit uzivatele a zamerit heslo', function () {
	$keycloak = KeycloakFactory::create(new TestSession());

	$query = query($keycloak->getLoginUrl(loginHint: 'jan@example.com', autoFocusPassword: true));

	Assert::same('jan@example.com', $query['login_hint']);
	Assert::same('autofocus-password', $query['ui_locales']);
});


test('bez napovedy se parametry nepridavaji', function () {
	$query = query(KeycloakFactory::create(new TestSession())->getLoginUrl());

	Assert::false(isset($query['login_hint']));
	Assert::false(isset($query['ui_locales']));
	Assert::false(isset($query['prompt']));
});


test('prazdna napoveda se ignoruje', function () {
	$query = query(KeycloakFactory::create(new TestSession())->getLoginUrl(loginHint: ''));

	Assert::false(isset($query['login_hint']));
});


test('zmena hesla jde pres Application-Initiated Action', function () {
	// Keycloak si sam vyzada re-autentizaci, ohlida politiku hesel i 2FA.
	$keycloak = KeycloakFactory::create(new TestSession());

	$query = query($keycloak->getUpdatePasswordUrl('/muj-ucet'));

	Assert::same('UPDATE_PASSWORD', $query['kc_action']);
	Assert::same('S256', $query['code_challenge_method']);
});


test('tiche prihlaseni pouziva prompt=none a vlastni navratovou adresu', function () {
	$keycloak = KeycloakFactory::create(new TestSession(), 'produkce');

	$query = query($keycloak->getSilentLoginUrl('/objednavky'));

	Assert::same('none', $query['prompt']);
	Assert::same('https://admin.example.com/keycloak-auth/silent-check/produkce', $query['redirect_uri']);
});


test('zkusebni pruchod se pozna az ze state', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	$bezny = query($keycloak->getSilentLoginUrl(null))['state'];
	$zkusebni = query($keycloak->getSilentLoginUrl(null, isTest: true))['state'];

	Assert::false($keycloak->isTestAuthState($bezny));
	Assert::true($keycloak->isTestAuthState($zkusebni));
});


test('odhlasovaci URL vznikne jen pri prihlaseni pres Keycloak', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	// Prazdna session - uzivatel pres Keycloak nesel.
	Assert::null($keycloak->getLogoutUrl());

	// Sekce existuje, ale id token v ni neni.
	section($session)->set(KeycloakSessionSection::AUTH_ATTEMPT_COUNT, 1);
	Assert::null($keycloak->getLogoutUrl());
});


test('odhlasovaci URL nese id token, klienta i navratovou adresu', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	section($session)->set(KeycloakSessionSection::ID_TOKEN, 'jwt-id-token');

	$url = $keycloak->getLogoutUrl();

	Assert::same('https://auth.example.com/realms/test-realm/protocol/openid-connect/logout', explode('?', $url)[0]);

	$query = query($url);
	Assert::same('jwt-id-token', $query['id_token_hint']);
	// client_id explicitne, aby Keycloak zvalidoval navratovou adresu i pri neplatnem id tokenu.
	Assert::same('admin-client', $query['client_id']);
	Assert::same('https://admin.example.com/keycloak-auth/post-log-out', $query['post_logout_redirect_uri']);
	Assert::same('https://admin.example.com/sign/in', $query['state']);
});


test('id token se po sestaveni odhlasovaci URL ze session maze', function () {
	// Odhlasit se da jen jednou; druhy pokus uz URL nesestavi.
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	section($session)->set(KeycloakSessionSection::ID_TOKEN, 'jwt-id-token');

	Assert::notNull($keycloak->getLogoutUrl());
	Assert::null(section($session)->get(KeycloakSessionSection::ID_TOKEN));
	Assert::null($keycloak->getLogoutUrl());
});


test('navratova adresa po odhlaseni jde urcit', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	section($session)->set(KeycloakSessionSection::ID_TOKEN, 'jwt-id-token');

	Assert::same('/rozlouceni', query($keycloak->getLogoutUrl('/rozlouceni'))['state']);
});


test('priznak prihlaseni neexistujiciho uzivatele se pri odhlaseni uklidi', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	section($session)->set(KeycloakSessionSection::NON_EXISTING_SSO_LOGIN, true);

	$keycloak->getLogoutUrl();

	Assert::null(section($session)->get(KeycloakSessionSection::NON_EXISTING_SSO_LOGIN));
});
