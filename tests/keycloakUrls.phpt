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


test('e-mail uzivatele jde do Keycloaku pres PAR, ne v URL', function () {
	// Nalez WEB-SSO-02: login_hint v URL koncil v historii prohlizece i v logu Keycloaku.
	$keycloak = KeycloakFactory::create(new TestSession(), 'produkce');

	$url = $keycloak->getLoginUrl(loginHint: 'jan@example.com', autoFocusPassword: true);

	Assert::notContains('jan', rawurldecode($url));
	Assert::same('https://auth.example.com/realms/test-realm/protocol/openid-connect/auth', explode('?', $url)[0]);
	Assert::same(
		['client_id' => 'admin-client', 'request_uri' => 'urn:ietf:params:oauth:request_uri:abc123'],
		query($url)
	);
});


test('PAR nese cely autorizacni request vcetne napovedy', function () {
	// Keycloak s request_uri pouzije jen parametry z PAR, nic z URL.
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session, 'produkce');

	$keycloak->getLoginUrl('/objednavky', 'jan@example.com', true);

	Assert::count(1, $keycloak->pushed);
	$pushed = $keycloak->pushed[0];
	Assert::same('jan@example.com', $pushed['login_hint']);
	Assert::same('autofocus-password', $pushed['ui_locales']);
	Assert::same('admin-client', $pushed['client_id']);
	Assert::same('code', $pushed['response_type']);
	Assert::same('openid email profile', $pushed['scope']);
	Assert::same('S256', $pushed['code_challenge_method']);
	Assert::same('https://admin.example.com/keycloak-auth/callback/produkce', $pushed['redirect_uri']);
	Assert::match('#^[A-Za-z0-9_-]+$#', $pushed['code_challenge']);

	// state z PAR je ten, ktery ceka callback - i s navratovou adresou
	Assert::same('/objednavky', $keycloak->consumeAuthState($pushed['state'])['backRedirect']);
});


test('client secret do parametru PAR nepridava volajici', function () {
	// Pridava si ho az samotne HTTP volani, aby se nedostal nikam, kam jdou parametry URL.
	$keycloak = KeycloakFactory::create(new TestSession());

	$keycloak->getLoginUrl(loginHint: 'jan@example.com');

	Assert::false(isset($keycloak->pushed[0]['client_secret']));
});


test('pri selhani PAR se pokracuje bez napovedy, ne s napovedou v URL', function () {
	$keycloak = KeycloakFactory::create(new TestSession());
	$keycloak->requestUri = null;

	$url = $keycloak->getLoginUrl(loginHint: 'jan@example.com', autoFocusPassword: true);

	Assert::notContains('jan', rawurldecode($url));
	$query = query($url);
	Assert::false(isset($query['login_hint']));
	Assert::false(isset($query['request_uri']));
	// jinak je to bezny autorizacni request, uzivatel se prihlasi, jen e-mail napise znovu
	Assert::same('admin-client', $query['client_id']);
	Assert::same('S256', $query['code_challenge_method']);
	Assert::same('autofocus-password', $query['ui_locales']);
	Assert::match('#^[0-9a-f]{32}$#', $query['state']);
});


test('bez napovedy se PAR nevola', function () {
	// V URL pak neni nic citliveho a prihlaseni neceka na dalsi volani Keycloaku.
	$keycloak = KeycloakFactory::create(new TestSession());

	$keycloak->getLoginUrl();
	$keycloak->getLoginUrl(loginHint: '');
	$keycloak->getUpdatePasswordUrl('/muj-ucet');

	Assert::same([], $keycloak->pushed);
});


test('zmena hesla s e-mailem posila kc_action v PAR', function () {
	$keycloak = KeycloakFactory::create(new TestSession());

	$url = $keycloak->getUpdatePasswordUrl('/muj-ucet', 'jan@example.com');

	Assert::notContains('jan', rawurldecode($url));
	Assert::false(isset(query($url)['kc_action']));
	Assert::same('UPDATE_PASSWORD', $keycloak->pushed[0]['kc_action']);
	Assert::same('jan@example.com', $keycloak->pushed[0]['login_hint']);
});


test('zamereni hesla bez napovedy zustava v URL', function () {
	$query = query(KeycloakFactory::create(new TestSession())->getLoginUrl(autoFocusPassword: true));

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
