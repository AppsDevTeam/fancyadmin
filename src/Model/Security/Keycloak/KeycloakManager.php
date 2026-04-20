<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Sso;
use Nette\Http\Session;

class KeycloakManager
{
	/** @var array<string, Keycloak> */
	private array $instances = [];

	private Session $session;

	public function __construct(Session $session)
	{
		$this->session = $session;
	}

	public function addInstance(string $name, Keycloak $instance): void
	{
		$this->instances[$name] = $instance;
	}

	/**
	 * Vrátí Keycloak instanci podle názvu.
	 */
	public function getInstance(string $name): ?Keycloak
	{
		return $this->instances[$name] ?? null;
	}

	/**
	 * Vrátí Keycloak instanci přiřazenou k dané identitě.
	 * Hledá nejprve na identitě (identity.sso), pak na jejích rolích (role.sso).
	 */
	public function getInstanceForIdentity(Identity $identity): ?Keycloak
	{
		// Přímá vazba na identitě
		$sso = $identity->getSso();
		if ($sso !== null) {
			return $this->getInstance($sso->getName());
		}

		// Fallback — SSO z role
		foreach ($identity->getRoles() as $role) {
			$sso = $role->getSso();
			if ($sso !== null) {
				return $this->getInstance($sso->getName());
			}
		}

		return null;
	}

	/**
	 * Vrátí Keycloak instanci přiřazenou k danému Sso záznamu.
	 */
	public function getInstanceForSso(Sso $sso): ?Keycloak
	{
		return $this->getInstance($sso->getName());
	}

	/**
	 * Vrátí Keycloak instanci, přes kterou se aktuální uživatel přihlásil.
	 * Čte z session.
	 */
	public function getInstanceFromSession(): ?Keycloak
	{
		if (!$this->session->hasSection(KeycloakSessionSection::SECTION_NAME)) {
			return null;
		}

		$keycloakSession = $this->session->getSection(KeycloakSessionSection::SECTION_NAME);
		$instanceName = $keycloakSession->get(KeycloakSessionSection::SSO_INSTANCE_NAME);

		if ($instanceName === null) {
			return null;
		}

		return $this->getInstance($instanceName);
	}

	/**
	 * Uloží název instance do session (při přihlášení).
	 */
	public function storeInstanceInSession(string $name): void
	{
		$keycloakSession = $this->session->getSection(KeycloakSessionSection::SECTION_NAME);
		$keycloakSession->set(KeycloakSessionSection::SSO_INSTANCE_NAME, $name);
	}

	/**
	 * Vrátí názvy všech registrovaných instancí.
	 *
	 * @return string[]
	 */
	public function getInstanceNames(): array
	{
		return array_keys($this->instances);
	}

	/**
	 * @return array<string, Keycloak>
	 */
	public function getInstances(): array
	{
		return $this->instances;
	}

	public function hasInstances(): bool
	{
		return count($this->instances) > 0;
	}
}
