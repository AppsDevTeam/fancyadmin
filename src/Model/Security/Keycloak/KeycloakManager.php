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
		private readonly bool $verifySsl = true,
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
	 *
	 * Neaktivní instance se s výchozím $activeOnly = true nevrací. Volající pak identitu
	 * ZÁMĚRNĚ obslouží jako běžného uživatele s heslem: přihlášení heslem, lokální obnova
	 * i změna hesla. Deaktivace instance je tedy zároveň nouzový režim, kdy se SSO
	 * uživatelé dostanou do aplikace i bez Keycloaku (viz README 18.2 "Proč isActive").
	 *
	 * $activeOnly = false je pro ukončení něčeho, co už běží (odhlášení uživatele
	 * přihlášeného před deaktivací), kde odstavená instance nesmí uživatele uvěznit.
	 */
	public function getInstanceForIdentity(Identity $identity, bool $activeOnly = true): ?Keycloak
	{
		if (!$this->identityRequiresSso($identity)) {
			return null;
		}

		$sso = $identity->getSso();
		if ($activeOnly && !$sso->getIsActive()) {
			return null;
		}

		return $this->getInstance($sso->getName());
	}

	/**
	 * Má identita SSO vazbu a alespoň jednu roli s needsSso? Nezávisí na tom, zda je
	 * instance aktivní.
	 */
	private function identityRequiresSso(Identity $identity): bool
	{
		if ($identity->getSso() === null) {
			return false;
		}

		foreach ($identity->getRoles() as $role) {
			if ($role->getNeedsSso()) {
				return true;
			}
		}

		return false;
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
	 * Instance, které se zapojují do přihlašování.
	 *
	 * Neaktivní se vynechávají. Silent SSO na přihlašovací stránce prochází tenhle seznam,
	 * takže jeden vadný záznam přesměruje login celé platformy - deaktivace je způsob, jak
	 * ho odstavit, aniž by se musel smazat (což u instance s navázanými identitami nejde).
	 *
	 * Pozor: filtr je záměrně JEN tady, ne v getInstance(). Podle názvu se instance
	 * dohledává i pro callback rozpracovaného requestu, odhlášení a backchannel logout -
	 * kdyby filtrovala i ta, deaktivace by uvěznila už přihlášené uživatele.
	 *
	 * @return Sso[]
	 */
	private function getAllSsoRecords(): array
	{
		return $this->em->getRepository($this->getSsoClass())->findBy(['isActive' => true]);
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
			verifySsl: $this->verifySsl,
		);
		$instance->setInstanceName($sso->getName());

		$this->instances[$sso->getName()] = $instance;
		return $instance;
	}
}
