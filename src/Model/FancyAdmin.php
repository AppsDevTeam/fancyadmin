<?php

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Security\SsoHostAllowlist;
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
		protected Resource $personalDataAclResource,
		protected ?string $context,
		protected array $jsComponentsConfig = [],
		protected array $colors = [],
		protected bool $keycloakEnabled = false,
		protected bool $passkeyEnabled = false,
		protected bool $passkeyEmailOtpEnabled = true,
		protected bool $passkeyEnrollmentRequired = false,
		protected ?string $passkeyRpId = null,
		protected ?string $passkeyRpName = null,
		protected array $ssoAllowedHosts = [],
		protected ?string $appIconsDir = null,
		protected bool $multiTenant = true,
	) {}

	/**
	 * Více účtů v jedné databázi (výchozí). Při false je projekt single-tenant:
	 * dotazy se nefiltrují podle účtu, účet se nevybírá a zákaznický portál
	 * (routy s `<selectedAccount>`) se nepoužívá.
	 */
	public function isMultiTenant(): bool
	{
		return $this->multiTenant;
	}

	public function getSsoHostAllowlist(): SsoHostAllowlist
	{
		return new SsoHostAllowlist($this->ssoAllowedHosts);
	}

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
	
	/**
	 * Smi se identita prihlasit do portalu - potrebuje opravneni do zakaznicke casti nebo do
	 * Backoffice. Jedina kontrola pro vsechny cesty do portalu (heslo, druhy faktor, passkey,
	 * jednorazovy token).
	 */
	public function canSignIn(Identity $identity): bool
	{
		// Single-tenant účet nevybírá. Účet vybraný z doby před přepnutím by do práv
		// započítal role profilu na tom účtu (Identity::getRoles()).
		if (!$this->multiTenant && $identity->getSelectedAccount()) {
			$identity->setSelectedAccount(null);
		}

		// Single-tenant nemá zákaznický portál - ten stojí na vybraném účtu v URL
		// (<selectedAccount>/...). Kdo nemá backoffice, neměl by kam přijít.
		if (!$this->multiTenant) {
			return $identity->isAllowed($this->backofficeAclResource);
		}

		return $identity->isAllowed($this->customerAclResource) || $identity->isAllowed($this->backofficeAclResource);
	}

	public function getFullDataAclResource(): Resource
	{
		return $this->fullDataAclResource;
	}

	public function getPersonalDataAclResource(): Resource
	{
		return $this->personalDataAclResource;
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

	/**
	 * Adresar (relativne k webrootu) s ikonami aplikace pro manifest: icon-192.png,
	 * icon-512.png a apple-touch-icon.png. Null = manifest vznika bez ikon.
	 */
	public function getAppIconsDir(): ?string
	{
		return $this->appIconsDir;
	}

	/**
	 * Obsah web manifestu: nazev a ikona aplikace pridane na plochu / domovskou obrazovku.
	 */
	public function createWebManifest(): array
	{
		$manifest = [
			'name' => $this->projectName,
			'short_name' => $this->projectName,
			'start_url' => '/',
			'display' => 'standalone',
			'lang' => 'cs-CZ',
			'dir' => 'auto',
		];

		if ($this->appIconsDir !== null) {
			$manifest['icons'] = [];
			foreach ([192, 512] as $size) {
				$manifest['icons'][] = [
					'src' => '/' . $this->appIconsDir . '/icon-' . $size . '.png',
					'sizes' => $size . 'x' . $size,
					'type' => 'image/png',
					'purpose' => 'any',
				];
			}
		}

		return $manifest;
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

	protected ?KeycloakManager $keycloakManager = null;

	public function setKeycloakManager(KeycloakManager $keycloakManager): void
	{
		$this->keycloakManager = $keycloakManager;
	}

	public function getKeycloakManager(): ?KeycloakManager
	{
		return $this->keycloakManager;
	}

	public function isKeycloakEnabled(): bool
	{
		return $this->keycloakEnabled;
	}

	public function isPasskeyEnabled(): bool
	{
		return $this->passkeyEnabled;
	}

	/** Záchranná cesta jednorázovým kódem na e-mail při vynuceném 2FA (README 19.9). */
	public function isPasskeyEmailOtpEnabled(): bool
	{
		return $this->passkeyEnabled && $this->passkeyEmailOtpEnabled;
	}

	/** Má být uživatel přihlášený jednorázovým kódem zamčený na Profilu, dokud si nepřidá klíč? */
	public function isPasskeyEnrollmentRequired(): bool
	{
		return $this->passkeyEnabled && $this->passkeyEnrollmentRequired;
	}

	public function getPasskeyRpId(): ?string
	{
		return $this->passkeyRpId;
	}

	public function getPasskeyRpName(): string
	{
		return $this->passkeyRpName ?? $this->projectName;
	}
}