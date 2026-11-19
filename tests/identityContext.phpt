<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakAuthentication;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\KeycloakFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentityQueryFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use ADT\FancyAdmin\Tests\Fixtures\TestSession;
use ADT\FancyAdmin\Tests\Fixtures\TestSso;
use ADT\FancyAdmin\UI\Components\Forms\PasskeyLoginTrait;
use Nette\Application\UI\Control;
use Tester\Assert;

/**
 * Identita podle e-mailu se hleda jen v kontextu aplikace.
 *
 * E-mail je unikatni jen v ramci kontextu - tentyz clovek muze mit identitu v kazdem
 * z nich. Dotaz bez kontextu pri prihlaseni spadl na NonUniqueResultException, a kdyby
 * nasel jedinou identitu z ciziho kontextu, prihlasil by do ni.
 */

require __DIR__ . '/bootstrap.php';


final class PasskeyLoginHost extends Control
{
	use PasskeyLoginTrait;

	public function __construct(TestIdentityQueryFactory $identityQueryFactory, string $context)
	{
		$this->_identityQueryFactory = $identityQueryFactory;
		$this->_fancyAdmin = FancyAdminFactory::create(['context' => $context]);
	}

	public function find(string $email): ?TestIdentity
	{
		return $this->findIdentityByEmail($email);
	}
}


function identity(string $email, ?string $context, ?string $ssoSub = null): TestIdentity
{
	return new TestIdentity()
		->setEmail($email)
		->setContext($context)
		->setSsoSub($ssoSub);
}

function createKeycloak(array $identities, string $context, TestEntityManager $em): Keycloak
{
	$keycloak = KeycloakFactory::create(new TestSession());

	foreach ([
		'identityQueryFactory' => new TestIdentityQueryFactory($identities),
		'fancyAdmin' => FancyAdminFactory::create(['context' => $context]),
		'securityUser' => new TestSecurityUser(),
		'em' => $em,
	] as $property => $value) {
		new ReflectionProperty(Keycloak::class, $property)->setValue($keycloak, $value);
	}

	return $keycloak;
}

function authentication(string $email, string $sub = 'sub-novy'): KeycloakAuthentication
{
	return new KeycloakAuthentication('access', 'refresh', 300, 1800, ['sub' => $sub, 'email' => $email]);
}


test('kontrola SSO pri prihlaseni najde identitu jen ve svem kontextu', function () {
	$cashdesk = identity('jan@example.com', 'cashdesk');
	$host = new PasskeyLoginHost(new TestIdentityQueryFactory([
		$cashdesk,
		identity('jan@example.com', 'paydroid'),
		identity('jan@example.com', 'paydroid'),
	]), 'cashdesk');

	Assert::same($cashdesk, $host->find('jan@example.com'));
});


test('identita z ciziho kontextu se pri kontrole SSO nenajde', function () {
	$host = new PasskeyLoginHost(new TestIdentityQueryFactory([
		identity('jan@example.com', 'paydroid'),
	]), 'cashdesk');

	Assert::null($host->find('jan@example.com'));
});


test('Keycloak paruje e-mailem jen identitu ze sveho kontextu', function () {
	$em = new TestEntityManager([TestIdentity::class, TestSso::class]);
	$paydroid = identity('jan@example.com', 'paydroid');
	$cashdesk = identity('jan@example.com', 'cashdesk');

	createKeycloak([$paydroid, $cashdesk], 'cashdesk', $em)->loginUser(authentication('jan@example.com'));

	Assert::same('sub-novy', $cashdesk->getSsoSub());
	Assert::null($paydroid->getSsoSub());
});


test('Keycloak nezalozi identitu do ciziho kontextu, ani kdyz e-mail existuje jinde', function () {
	$em = new TestEntityManager([TestIdentity::class, TestSso::class]);
	$paydroid = identity('jan@example.com', 'paydroid');

	Assert::exception(
		fn() => createKeycloak([$paydroid], 'cashdesk', $em)->loginUser(authentication('jan@example.com')),
		Nette\Security\AuthenticationException::class,
	);
	Assert::null($paydroid->getSsoSub());
});


test('automaticky zalozena identita dostane kontext aplikace', function () {
	$em = new TestEntityManager([TestIdentity::class, TestSso::class]);

	createKeycloak([], 'cashdesk', $em)->loginUser(authentication('jan@example.com'), autoRegister: true);

	Assert::count(1, $em->persisted);
	Assert::same('cashdesk', $em->persisted[0]->getContext());
});
