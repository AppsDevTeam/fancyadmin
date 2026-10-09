<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestAccount;
use ADT\FancyAdmin\Tests\Fixtures\TestAclRole;
use ADT\FancyAdmin\Tests\Fixtures\TestApiKey;
use ADT\FancyAdmin\Tests\Fixtures\TestSso;
use Tester\Assert;

/**
 * ApiKeyTrait a SsoTrait.
 */

require __DIR__ . '/bootstrap.php';


test('API klic ma nazev a nepovinny otisk', function () {
	$apiKey = new TestApiKey('Integrace');

	Assert::same('Integrace', $apiKey->getName());
	// V databazi je jen otisk, dokud se klic nevygeneruje, je sloupec prazdny.
	Assert::null($apiKey->getKey());

	$apiKey->setName('Jina integrace')->setKey(hash('sha256', 'raw'));

	Assert::same('Jina integrace', $apiKey->getName());
	Assert::same(hash('sha256', 'raw'), $apiKey->getKey());
	Assert::null($apiKey->setKey(null)->getKey());
});


test('API klic muze patrit uctu', function () {
	$apiKey = new TestApiKey();
	$account = new TestAccount('Firma');

	Assert::null($apiKey->getAccount());
	Assert::same($account, $apiKey->setAccount($account)->getAccount());
	Assert::null($apiKey->setAccount(null)->getAccount());
});


test('SSO instance nese celou konfiguraci Keycloaku', function () {
	$sso = new TestSso('produkce', 'https://auth.example.com');

	Assert::same('produkce', $sso->getName());
	Assert::same('test-realm', $sso->getRealm());
	Assert::same('https://internal.example.com', $sso->getBaseUrl());
	Assert::same('https://auth.example.com', $sso->getHostUrl());
	Assert::same('client', $sso->getClientId());
	Assert::same('secret', $sso->getClientSecret());
	Assert::same('frontend', $sso->getFrontendClientId());
});


test('konfigurace SSO jde prepsat', function () {
	$sso = new TestSso();

	$sso->setName('jina')
		->setRealm('jiny-realm')
		->setBaseUrl('https://internal2.example.com')
		->setHostUrl('https://auth2.example.com')
		->setClientId('client2')
		->setClientSecret('secret2')
		->setFrontendClientId('frontend2');

	Assert::same('jina', $sso->getName());
	Assert::same('jiny-realm', $sso->getRealm());
	Assert::same('https://internal2.example.com', $sso->getBaseUrl());
	Assert::same('https://auth2.example.com', $sso->getHostUrl());
	Assert::same('client2', $sso->getClientId());
	Assert::same('secret2', $sso->getClientSecret());
	Assert::same('frontend2', $sso->getFrontendClientId());
});


test('SSO instance je ve vychozim stavu aktivni', function () {
	// Deaktivace je zpusob, jak vadnou instanci odstavit z prihlasovani, aniz by se smazala.
	$sso = new TestSso();

	Assert::true($sso->getIsActive());
	Assert::false($sso->setIsActive(false)->getIsActive());
});


test('vychozi role noveho uzivatele je nepovinna', function () {
	$sso = new TestSso();
	$role = new TestAclRole('sso-uzivatel');

	Assert::null($sso->getDefaultRole());
	Assert::same($role, $sso->setDefaultRole($role)->getDefaultRole());
	Assert::null($sso->setDefaultRole(null)->getDefaultRole());
});
