<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use Nette\Http\Session;
use Nette\Localization\Translator;
use ReflectionClass;

/**
 * PasskeyService bez EntityManageru - testovane metody na nej nesahaji, takze ho
 * nemusime skladat. Chranene metody zpristupnuje pres call*().
 */
final class TestPasskeyService extends PasskeyService
{
	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct(
		FancyAdmin $fancyAdmin,
		Translator $translator,
		?Session $session = null,
		?PasskeyQueryFactory $passkeyQueryFactory = null,
	) {
		$this->fancyAdmin = $fancyAdmin;
		$this->translator = $translator;
		$this->passkeyQueryFactory = $passkeyQueryFactory;

		if ($session !== null) {
			$this->session = $session;
		}
	}

	public function callNormalizeName(string $name): string
	{
		return $this->normalizeName($name);
	}

	public function callAssertHasPasskeys(Identity $identity): Identity&HasPasskeys
	{
		return $this->assertHasPasskeys($identity);
	}

	public function callGetPasskeyQueryFactory(): PasskeyQueryFactory
	{
		return $this->getPasskeyQueryFactory();
	}

	/** @return \ADT\FancyAdmin\Model\Entities\AclRole[] */
	public function callGetAllRoles(Identity $identity): array
	{
		return $this->getAllRoles($identity);
	}

	public function callStoreChallenge(string $key, string $challenge): void
	{
		$this->storeChallenge($key, $challenge);
	}

	public function callConsumeChallenge(string $key): string
	{
		return $this->consumeChallenge($key);
	}
}

/**
 * KeycloakManager, ktery jen odpovi, jestli k identite SSO instance patri - skladat
 * skutecnou instanci (a k ni celou Keycloak konfiguraci) by tady nic nepridalo.
 */
final class TestKeycloakManager extends KeycloakManager
{
	private ?Keycloak $instance;

	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct(bool $hasInstanceForIdentity = true)
	{
		$this->instance = $hasInstanceForIdentity
			? new ReflectionClass(Keycloak::class)->newInstanceWithoutConstructor()
			: null;
	}

	public function getInstanceForIdentity(Identity $identity, bool $activeOnly = true): ?Keycloak
	{
		return $this->instance;
	}
}
