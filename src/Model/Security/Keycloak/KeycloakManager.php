<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use Nette\Application\LinkGenerator;
use Nette\Caching\Storage;
use Nette\Http\Session;

class KeycloakManager
{
	/** @var array<string, Keycloak> */
	private array $instances = [];

	private ?string $ssoClass = null;

	public function __construct(
		private readonly EntityManager $em,
		private readonly LinkGenerator $linkGenerator,
		private readonly SecurityUser $securityUser,
		private readonly IdentityQueryFactory $identityQueryFactory,
		private readonly AclRoleQueryFactory $aclRoleQueryFactory,
		private readonly FancyAdmin $fancyAdmin,
		private readonly Session $session,
		private readonly Storage $storage,
	) {}

	/**
	 * Vrátí Keycloak instanci podle názvu. Vytvoří ji lazy z DB.
	 */
	public function getInstance(string $name): ?Keycloak
	{
		if (isset($this->instances[$name])) {
			return $this->instances[$name];
		}

		$sso = $this->findSso($name);
		if ($sso === null) {
			return null;
		}

		return $this->createInstanceFromSso($sso);
	}

	/**
	 * Vrátí Keycloak instanci přiřazenou k dané identitě.
	 * Identita musí mít nastavenou SSO vazbu (identity.sso) a zároveň
	 * alespoň jedna její role musí mít needsSso = true.
	 */
	public function getInstanceForIdentity(Identity $identity): ?Keycloak
	{
		$sso = $identity->getSso();
		if ($sso === null) {
			return null;
		}

		// Zkontrolujeme, zda alespoň jedna role vyžaduje SSO
		$needsSso = false;
		foreach ($identity->getRoles() as $role) {
			if ($role->getNeedsSso()) {
				$needsSso = true;
				break;
			}
		}

		if (!$needsSso) {
			return null;
		}

		return $this->getInstance($sso->getName());
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

	public function hasInstances(): bool
	{
		return count($this->getAllSsoRecords()) > 0;
	}

	/**
	 * Vrátí názvy všech dostupných SSO instancí.
	 *
	 * @return string[]
	 */
	public function getInstanceNames(): array
	{
		return array_map(
			fn (Sso $sso) => $sso->getName(),
			$this->getAllSsoRecords()
		);
	}

	/**
	 * @return Sso[]
	 */
	private function getAllSsoRecords(): array
	{
		return $this->em->getRepository($this->getSsoClass())->findAll();
	}

	private function findSso(string $name): ?Sso
	{
		return $this->em->getRepository($this->getSsoClass())->findOneBy(['name' => $name]);
	}

	private function getSsoClass(): string
	{
		if ($this->ssoClass === null) {
			$this->ssoClass = $this->em->findEntityClassByInterface(Sso::class);
		}
		return $this->ssoClass;
	}

	private function createInstanceFromSso(Sso $sso): Keycloak
	{
		$instance = new Keycloak(
			realm: $sso->getRealm(),
			baseUrl: $sso->getBaseUrl(),
			hostUrl: $sso->getHostUrl(),
			clientId: $sso->getClientId(),
			clientSecret: $sso->getClientSecret(),
			frontendClientId: $sso->getFrontendClientId(),
			em: $this->em,
			linkGenerator: $this->linkGenerator,
			securityUser: $this->securityUser,
			identityQueryFactory: $this->identityQueryFactory,
			aclRoleQueryFactory: $this->aclRoleQueryFactory,
			fancyAdmin: $this->fancyAdmin,
			session: $this->session,
			storage: $this->storage,
		);
		$instance->setInstanceName($sso->getName());

		$this->instances[$sso->getName()] = $instance;
		return $instance;
	}
}
