<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use Doctrine\ORM\Mapping as ORM;

trait SsoTrait
{
	/**
	 * Zapojuje se instance do přihlašování?
	 *
	 * Silent SSO na přihlašovací stránce prochází všechny aktivní instance, takže vadná
	 * konfigurace ovlivní login celé platformy. Deaktivace je způsob, jak takovou instanci
	 * odstavit, aniž by se musela smazat - konfigurace i navázané identity zůstanou.
	 * Viz KeycloakManager::getAllSsoRecords().
	 */
	use IsActive;

	#[ORM\Column(unique: true, nullable: false)]
	#[LoggableProperty]
	protected string $name;

	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	protected string $realm;

	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	protected string $baseUrl;

	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	protected string $hostUrl;

	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	protected string $clientId;

	#[ORM\Column(nullable: false)]
	protected string $clientSecret;

	#[ORM\Column(nullable: false)]
	#[LoggableProperty]
	protected string $frontendClientId;

	#[ORM\ManyToOne(targetEntity: 'AclRole')]
	#[ORM\JoinColumn(nullable: true)]
	#[LoggableProperty]
	protected ?AclRole $defaultRole = null;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getRealm(): string
	{
		return $this->realm;
	}

	public function setRealm(string $realm): static
	{
		$this->realm = $realm;
		return $this;
	}

	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	public function setBaseUrl(string $baseUrl): static
	{
		$this->baseUrl = $baseUrl;
		return $this;
	}

	public function getHostUrl(): string
	{
		return $this->hostUrl;
	}

	public function setHostUrl(string $hostUrl): static
	{
		$this->hostUrl = $hostUrl;
		return $this;
	}

	public function getClientId(): string
	{
		return $this->clientId;
	}

	public function setClientId(string $clientId): static
	{
		$this->clientId = $clientId;
		return $this;
	}

	public function getClientSecret(): string
	{
		return $this->clientSecret;
	}

	public function setClientSecret(string $clientSecret): static
	{
		$this->clientSecret = $clientSecret;
		return $this;
	}

	public function getFrontendClientId(): string
	{
		return $this->frontendClientId;
	}

	public function setFrontendClientId(string $frontendClientId): static
	{
		$this->frontendClientId = $frontendClientId;
		return $this;
	}

	public function getDefaultRole(): ?AclRole
	{
		return $this->defaultRole;
	}

	public function setDefaultRole(?AclRole $defaultRole): static
	{
		$this->defaultRole = $defaultRole;
		return $this;
	}

}
