<?php

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use Nette\Security\Resource;

class FancyAdmin
{
	public function __construct(
		protected string $project,
		protected string $projectName,
		protected string $adminHostPath,
		protected bool $lostPasswordEnabled,
		protected string $logoPublicPath,
		protected string $logoMenuPath,
		protected string $emailBackgroundColor,
		protected ?string $faviconFileNamePng,
		protected ?string $faviconFileNameSvg,
		protected string $loginPageLogoPath,
		protected string $logoBitmapPublicPath,
		protected string $defaultCustomerRoute,
		protected string $defaultBackofficeRoute,
		protected bool $hmr,
		protected Resource $customerAclResource,
		protected Resource $backofficeAclResource,
		protected Resource $fullDataAclResource,
		protected ?string $context,
		protected array $jsComponentsConfig = [],
		protected array $colors = [],
		protected ?array $keycloak = null,
	) {}

	public function getProject(): string
	{
		return $this->project;
	}
	
	public function getProjectName(): string
	{
		return $this->projectName;
	}

	public function getLogoPublicPath(): string
	{
		return $this->logoPublicPath;
	}

	public function getLogoMenuPath(): string
	{
		return $this->logoMenuPath;
	}

	public function getLoginPageLogoPath(): string
	{
		return $this->loginPageLogoPath;
	}

	public function getLogoBitmapPublicPath(): string
	{
		return $this->logoBitmapPublicPath;
	}

	public function getAdminHostPath(): string
	{
		return $this->adminHostPath;
	}

	public function isLostPasswordEnabled(): bool
	{
		return $this->lostPasswordEnabled;
	}

	public function getDefaultCustomerRoute(): string
	{
		return $this->defaultCustomerRoute;
	}

	public function getDefaultBackofficeRoute(): string
	{
		return $this->defaultBackofficeRoute;
	}

	public function getHmr(): bool
	{
		return $this->hmr;
	}
	
	public function getContext(): ?string
	{
		return $this->context;
	}

	public function getCustomerAclResource(): Resource
	{
		return $this->customerAclResource;
	}

	public function getBackofficeAclResource(): Resource
	{
		return $this->backofficeAclResource;
	}
	
	public function getFullDataAclResource(): Resource
	{
		return $this->fullDataAclResource;
	}

	public function getJsComponentsConfig(): array
	{
		return $this->jsComponentsConfig;
	}

	public function setJsComponentsConfig(array $jsComponentsConfig): self
	{
		$this->jsComponentsConfig = $jsComponentsConfig;
		return $this;
	}

	public function getEmailBackgroundColor(): string
	{
		return $this->emailBackgroundColor;
	}

	public function setEmailBackgroundColor(string $emailBackgroundColor): static
	{
		$this->emailBackgroundColor = $emailBackgroundColor;
		return $this;
	}

	public function getFaviconFileNamePng(): ?string
	{
		return $this->faviconFileNamePng;
	}

	public function setFaviconFileNamePng(string $faviconFileNamePng): static
	{
		$this->faviconFileNamePng = $faviconFileNamePng;
		return $this;
	}

	public function getFaviconFileNameSvg(): ?string
	{
		return $this->faviconFileNameSvg;
	}

	public function setFaviconFileNameSvg(string $faviconFileNameSvg): static
	{
		$this->faviconFileNameSvg = $faviconFileNameSvg;
		return $this;
	}

	public function getColors(): array
	{
		return $this->colors;
	}

	protected ?Keycloak $keycloakService = null;

	public function setKeycloak(Keycloak $keycloak): void
	{
		$this->keycloakService = $keycloak;
	}

	public function getKeycloak(): ?Keycloak
	{
		return $this->keycloakService;
	}

	public function isKeycloakEnabled(): bool
	{
		return $this->keycloak !== null;
	}

	public function getKeycloakRealm(): ?string
	{
		return $this->keycloak['realm'] ?? null;
	}

	public function getKeycloakHostUrl(): ?string
	{
		return $this->keycloak['hostUrl'] ?? null;
	}

	public function getKeycloakFrontendClientId(): ?string
	{
		return $this->keycloak['frontendClientId'] ?? null;
	}
}