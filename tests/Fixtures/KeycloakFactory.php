<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Http\UrlScript;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Keycloak bez konstruktoru.
 *
 * Konstruktor si zaklada Guzzle klienta, a ten je v balicku jen "suggest" - v testovacim
 * prostredi nemusi byt nainstalovany. Metody, ktere se tady testuji (sestaveni URL a prace
 * se state v session), po HTTP klientovi stejne nesahaji.
 */
final class KeycloakFactory
{
	public const string HOST_URL = 'https://auth.example.com';
	public const string REALM = 'test-realm';
	public const string CLIENT_ID = 'admin-client';

	public static function create(TestSession $session, string $instanceName = 'default'): Keycloak
	{
		$keycloak = new ReflectionClass(Keycloak::class)->newInstanceWithoutConstructor();

		self::set($keycloak, 'realm', self::REALM);
		self::set($keycloak, 'baseUrl', 'https://internal.example.com');
		self::set($keycloak, 'hostUrl', self::HOST_URL);
		self::set($keycloak, 'clientId', self::CLIENT_ID);
		self::set($keycloak, 'clientSecret', 'tajemstvi');
		self::set($keycloak, 'frontendClientId', 'frontend-client');
		self::set($keycloak, 'session', $session);
		self::set($keycloak, 'linkGenerator', self::createLinkGenerator());

		$keycloak->setInstanceName($instanceName);

		return $keycloak;
	}

	public static function createLinkGenerator(): LinkGenerator
	{
		$router = new RouteList('Portal');
		$router->addRoute('<presenter>/<action>[/<instance>]', ['presenter' => 'Home', 'action' => 'default']);

		return new LinkGenerator($router, new UrlScript('https://admin.example.com/'));
	}

	/** @return array{0: string, 1: string} [state, code_challenge] */
	public static function createAuthState(Keycloak $keycloak, ?string $backRedirect = null, bool $isTest = false): array
	{
		return new ReflectionMethod($keycloak, 'createAuthState')->invoke($keycloak, $backRedirect, $isTest);
	}

	private static function set(Keycloak $keycloak, string $property, mixed $value): void
	{
		new ReflectionProperty(Keycloak::class, $property)->setValue($keycloak, $value);
	}
}
