<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\Tests\Fixtures\KeycloakFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestSession;
use Tester\Assert;

/**
 * Rozpracovany autorizacni request (state + PKCE) drzeny v session.
 *
 * State je CSRF token, ktery callback overuje proti session a hned zneplatnuje. Navratova
 * URL se do Keycloaku vubec neposila - drzi se u state, takze ji nejde podvrhnout.
 */

require __DIR__ . '/bootstrap.php';


function authStates(TestSession $session): array
{
	return $session->getSection(KeycloakSessionSection::SECTION_NAME)->get(KeycloakSessionSection::AUTH_STATES) ?? [];
}

function base64Url(string $data): string
{
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}


test('state je nahodny a code challenge odpovida verifieru', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	[$state, $codeChallenge] = KeycloakFactory::createAuthState($keycloak, '/objednavky');

	Assert::match('#^[0-9a-f]{32}$#', $state);

	$entry = authStates($session)[$state];
	// PKCE S256: challenge = base64url(sha256(verifier))
	Assert::same(base64Url(hash('sha256', $entry['verifier'], true)), $codeChallenge);
	// Challenge ani verifier nesmi obsahovat znaky, ktere by se v URL musely kodovat.
	Assert::match('#^[A-Za-z0-9_-]+$#', $codeChallenge);
	Assert::match('#^[A-Za-z0-9_-]+$#', $entry['verifier']);
});


test('kontext requestu zna navratovou URL, instanci i cas', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session, 'produkce');

	[$state] = KeycloakFactory::createAuthState($keycloak, '/objednavky');

	$entry = authStates($session)[$state];
	Assert::same('/objednavky', $entry['backRedirect']);
	Assert::same('produkce', $entry['instance']);
	Assert::false($entry['isTest']);
	Assert::true(abs($entry['time'] - time()) <= 1);
});


test('kazdy request ma vlastni state i verifier', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	[$prvniState] = KeycloakFactory::createAuthState($keycloak, null);
	[$druhyState] = KeycloakFactory::createAuthState($keycloak, null);

	Assert::notSame($prvniState, $druhyState);
	Assert::notSame(authStates($session)[$prvniState]['verifier'], authStates($session)[$druhyState]['verifier']);
	Assert::count(2, authStates($session));
});


test('state se da vyzvednout jen jednou', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	[$state] = KeycloakFactory::createAuthState($keycloak, '/objednavky');

	$entry = $keycloak->consumeAuthState($state);

	Assert::same('/objednavky', $entry['backRedirect']);
	Assert::same([], authStates($session));
	Assert::null($keycloak->consumeAuthState($state));
});


test('neznamy nebo chybejici state neprojde', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	Assert::null($keycloak->consumeAuthState(null));
	// Prazdna session - sekce jeste neexistuje.
	Assert::null($keycloak->consumeAuthState('cokoliv'));

	KeycloakFactory::createAuthState($keycloak, null);
	Assert::null($keycloak->consumeAuthState('podvrzeny-state'));
});


test('state patrici jine instanci neprojde', function () {
	// Callback jedne instance nesmi prijmout request rozpracovany u druhe.
	$session = new TestSession();
	$produkce = KeycloakFactory::create($session, 'produkce');
	$test = KeycloakFactory::create($session, 'test');

	[$state] = KeycloakFactory::createAuthState($produkce, null);

	Assert::null($test->consumeAuthState($state));
	// State se stejne spotreboval - podvrzeny pokus ho zneplatni i pro spravnou instanci.
	Assert::null($produkce->consumeAuthState($state));
});


test('expirovany state neprojde', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	[$state] = KeycloakFactory::createAuthState($keycloak, null);

	$states = authStates($session);
	$states[$state]['time'] = time() - 601;
	$session->getSection(KeycloakSessionSection::SECTION_NAME)->set(KeycloakSessionSection::AUTH_STATES, $states);

	Assert::null($keycloak->consumeAuthState($state));
});


test('state tesne pred expiraci jeste projde', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	[$state] = KeycloakFactory::createAuthState($keycloak, null);

	$states = authStates($session);
	$states[$state]['time'] = time() - 599;
	$session->getSection(KeycloakSessionSection::SECTION_NAME)->set(KeycloakSessionSection::AUTH_STATES, $states);

	Assert::notNull($keycloak->consumeAuthState($state));
});


test('expirovane requesty se pri zalozeni noveho uklizi', function () {
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	[$stary] = KeycloakFactory::createAuthState($keycloak, null);

	$states = authStates($session);
	$states[$stary]['time'] = time() - 601;
	$session->getSection(KeycloakSessionSection::SECTION_NAME)->set(KeycloakSessionSection::AUTH_STATES, $states);

	KeycloakFactory::createAuthState($keycloak, null);

	Assert::false(array_key_exists($stary, authStates($session)));
});


test('pocet rozpracovanych requestu je omezeny', function () {
	// Ochrana proti nafouknuti session - bez ni by stacilo nacist prihlasovaci stranku dokola.
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	$states = [];
	for ($i = 0; $i < 25; $i++) {
		[$states[]] = KeycloakFactory::createAuthState($keycloak, null);
	}

	Assert::count(10, authStates($session));
	// Nejnovejsi zustavaji, nejstarsi padaji.
	Assert::true(array_key_exists($states[24], authStates($session)));
	Assert::false(array_key_exists($states[0], authStates($session)));
});


test('zkusebni pruchod se pozna podle state, ne podle URL', function () {
	// Priznak se drzi v session u state, aby ho neslo podvrhnout z venku.
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);

	[$bezny] = KeycloakFactory::createAuthState($keycloak, null);
	[$zkusebni] = KeycloakFactory::createAuthState($keycloak, null, isTest: true);

	Assert::false($keycloak->isTestAuthState($bezny));
	Assert::true($keycloak->isTestAuthState($zkusebni));
});


test('overeni zkusebniho pruchodu state nespotrebuje', function () {
	// Spotrebovat ho musi az zvolena vetev zpracovani, jinak by CSRF ochrana prestala platit.
	$session = new TestSession();
	$keycloak = KeycloakFactory::create($session);
	[$state] = KeycloakFactory::createAuthState($keycloak, null, isTest: true);

	Assert::true($keycloak->isTestAuthState($state));
	Assert::true($keycloak->isTestAuthState($state));
	Assert::notNull($keycloak->consumeAuthState($state));
});


test('zkusebni pruchod cizi nebo prosly se nepozna jako zkusebni', function () {
	$session = new TestSession();
	$produkce = KeycloakFactory::create($session, 'produkce');
	$test = KeycloakFactory::create($session, 'test');
	[$state] = KeycloakFactory::createAuthState($produkce, null, isTest: true);

	Assert::false($test->isTestAuthState($state));
	Assert::false($produkce->isTestAuthState(null));
	Assert::false($produkce->isTestAuthState('neexistuje'));

	$states = authStates($session);
	$states[$state]['time'] = time() - 601;
	$session->getSection(KeycloakSessionSection::SECTION_NAME)->set(KeycloakSessionSection::AUTH_STATES, $states);
	Assert::false($produkce->isTestAuthState($state));
});


test('parametry zkusebniho pruchodu', function () {
	Assert::same('ssoTest', Keycloak::SSO_TEST_PARAM);
	Assert::same('ok', Keycloak::SSO_TEST_OK);
});
