<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\DI;

use ADT\FancyAdmin\Console\CreateIdentityCommand;
use ADT\FancyAdmin\Console\GenerateMissingAclResourcesCommand;
use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Audit\ExportAuditLoggerAdapter;
use ADT\FancyAdmin\Model\Entities\AclResource;
use ADT\FancyAdmin\Model\Entities\AclResourceTrait;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\AclRoleTrait;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\IdentityPasskeysTrait;
use ADT\FancyAdmin\Model\Entities\IdentityTrait;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\Entities\ProfileTrait;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use ADT\FancyAdmin\Model\Security\Authenticator;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Model\Services\JsComponents;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\Forms\Controls\PasswordRevealInput;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;
use Nette\DI\Config\Loader;
use Nette\DI\Definitions\Statement;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Security\Resource;
use ReflectionClass;
use RuntimeException;

class FancyAdminExtension extends CompilerExtension implements TranslationProviderInterface
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'project' => Expect::string()->default(null),
			'projectName' => Expect::string()->default(null),
			'adminHostPath' => Expect::string()->default(null),
			'defaultCustomerRoute' => Expect::string()->default(':PortalCustomer:Home:'),
			'defaultBackofficeRoute' => Expect::string()->default(':PortalBackoffice:Home:'),
			'lostPasswordEnabled' => Expect::bool()->default(true),
			'logoPublicPath' => Expect::string()->default(null),
			'logoMenuPath' => Expect::string()->default(null),
			'loginPageLogoPath' => Expect::string()->default(null),
			'logoBitmapPublicPath' => Expect::string()->default(null),
			'emailBackgroundColor' => Expect::string()->default(null),
			'faviconFileNamePng' => Expect::string()->nullable()->default(null),
			'faviconFileNameSvg' => Expect::string()->nullable()->default(null),
			'hmr' => Expect::bool()->default(false),
			'customerAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::CUSTOMER_DASHBOARD),
			'backofficeAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::BACKOFFICE_DASHBOARD),
			'fullDataAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::FULL_DATA),
			'context' => Expect::string()->default(null),
			'jsComponentsConfig' => Expect::array()->default([]),
			'locksDir' => Expect::string()->required(),
			'keycloakEnabled' => Expect::bool()->default(false),
			// Vypnutí validace TLS certifikátu Keycloak serveru — POUZE pro lokální vývoj (self-signed cert)
			'keycloakVerifySsl' => Expect::bool()->default(true),
			'passkeyEnabled' => Expect::bool()->default(false),
			// WebAuthn Relying Party ID (doména) — když není nastaveno, odvodí se za běhu host z adminHostPath
			'passkeyRpId' => Expect::string()->nullable()->default(null),
			// WebAuthn Relying Party name — když není nastaveno, použije se projectName
			'passkeyRpName' => Expect::string()->nullable()->default(null),
			'colors' => Expect::structure([
				'backgroundColor' => Expect::string()->required(),
				'dashboardAccentColor' => Expect::string()->required(),
				'primaryColor' => Expect::string()->required(),
				'primaryColorDark' => Expect::string()->required(),
				'primaryColorDark20' => Expect::string()->required(),
				'secondaryColor' => Expect::string()->required(),
				'secondaryColorDark' => Expect::string()->required(),
				'secondaryColorDarker' => Expect::string()->required(),
				'ternaryColor' => Expect::string()->required(),
				'ternaryTextColor' => Expect::string()->required(),
				'loginBackground' => Expect::string()->required(),
				'loginInputTextColor' => Expect::string()->required(),
				'loginBackgroundInput' => Expect::string()->required(),
				'loginBackgroundInputFocus' => Expect::string()->required(),
				'inputBorder' => Expect::string()->required(),
				'inputFocusBorder' => Expect::string()->required(),
				'inputFocusBackground' => Expect::string()->required(),
				// Nepovinne barvy. Pri null si _sidepanel.scss / _login.scss / layout
				// drzi puvodni hodnoty, takze existujici projekty se nemeni.
				'sidePanelItemColor' => Expect::string()->nullable()->default(null),
				'textColor' => Expect::string()->nullable()->default(null),
				'loginPageBackground' => Expect::string()->nullable()->default(null),
				'loginPageTextColor' => Expect::string()->nullable()->default(null),
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$this->config = (new Processor)->process($this->getConfigSchema(), $this->config);

		$builder->addFactoryDefinition($this->prefix('sidePanelControlFactory'))
			->setImplement(SidePanelControlFactory::class)
			->getResultDefinition()
			->setFactory(SidePanelControl::class);

		$builder->addDefinition($this->prefix('fancyAdminRouter'))
			->setFactory(FancyAdminRouter::class);

		$builder->addDefinition($this->prefix('administration'))
			->setFactory(FancyAdmin::class, [
				'project' => $this->config->project,
				'projectName' => $this->config->projectName,
				'adminHostPath' => $this->config->adminHostPath,
				'logoPublicPath' => $this->config->logoPublicPath,
				'logoMenuPath' => $this->config->logoMenuPath,
				'loginPageLogoPath' => $this->config->loginPageLogoPath,
				'logoBitmapPublicPath' => $this->config->logoBitmapPublicPath,
				'lostPasswordEnabled' => $this->config->lostPasswordEnabled,
				'defaultCustomerRoute' => $this->config->defaultCustomerRoute,
				'defaultBackofficeRoute' => $this->config->defaultBackofficeRoute,
				'emailBackgroundColor' => $this->config->emailBackgroundColor,
				'faviconFileNamePng' => $this->config->faviconFileNamePng,
				'faviconFileNameSvg' => $this->config->faviconFileNameSvg,
				'hmr' => $this->config->hmr,
				'customerAclResource' => $this->config->customerAclResource,
				'backofficeAclResource' => $this->config->backofficeAclResource,
				'fullDataAclResource' => $this->config->fullDataAclResource,
				'jsComponentsConfig' => $this->config->jsComponentsConfig,
				'context' => $this->config->context,
				'colors' => (array) $this->config->colors,
				'keycloakEnabled' => $this->config->keycloakEnabled,
				'passkeyEnabled' => $this->config->passkeyEnabled,
				'passkeyRpId' => $this->config->passkeyRpId,
				'passkeyRpName' => $this->config->passkeyRpName,
			]);

		$builder->addDefinition($this->prefix('jsComponents'))
			->setFactory(JsComponents::class);

		$builder->addDefinition($this->prefix('passkeyService'))
			->setFactory(PasskeyService::class);

		// Jednotny auditni stream (tabulka audit_log). Knihovny si deklaruji
		// vlastni rozhrani a nezavisi na fancyadminu - naplni ho adaptery.
		$builder->addDefinition($this->prefix('auditLogger'))
			->setFactory(AuditLogger::class);

		// adt/exporter je volitelna zavislost - adapter registrujeme jen
		// tehdy, kdyz je jeho rozhrani k dispozici
		if (interface_exists(\ADT\Exporter\Model\Service\ExportAuditLogger::class)) {
			$builder->addDefinition($this->prefix('exportAuditLogger'))
				->setFactory(ExportAuditLoggerAdapter::class);
		}

		// Keycloak — registrace KeycloakManager (instance se vytváří lazy z DB)
		if ($this->config->keycloakEnabled) {
			$builder->addDefinition($this->prefix('keycloakManager'))
				->setFactory(KeycloakManager::class)
				->setArgument('verifySsl', $this->config->keycloakVerifySsl);
		}

		//$this->validateTraitInterfaceCompliance();

		// command registration

		$defs[] = $builder->addDefinition($this->prefix('createIdentity'))
			->setFactory(CreateIdentityCommand::class)
			->setAutowired(false);

		$defs[] = $builder->addDefinition($this->prefix('generateMissingAclResources'))
			->setFactory(GenerateMissingAclResourcesCommand::class, [
				'appDir' => $builder->parameters['appDir'],
			])
			->setAutowired(false);

		foreach ($defs as $_def) {
			$_def->addSetup('setLocksDir', [$this->config->locksDir]);
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$securityUserDef = $builder->getDefinitionByType(SecurityUser::class);
		$securityUserDef->addSetup('setFullDataAclResource', [$this->config->fullDataAclResource]);
		$securityUserDef->addSetup('setBackofficeAclResource', [$this->config->backofficeAclResource]);

		$authenticatorDef = $builder->getDefinitionByType(Authenticator::class);
		$authenticatorDef->addSetup('setFancyAdmin', [$this->prefix('@administration')]);
		$authenticatorDef->addSetup('setExpirationCallback', [
			new Statement('Closure::fromCallable', ['@fancyadmin.sessionExpirationCallback']),
		]);

		if ($this->config->keycloakEnabled) {
			$fancyAdminDef = $builder->getDefinition($this->prefix('administration'));
			$fancyAdminDef->addSetup('setKeycloakManager', [$this->prefix('@keycloakManager')]);
		}

		// passkeyEnabled vyžaduje passkey infrastrukturu v projektu — srozumitelná chyba
		// při kompilaci kontejneru místo kryptické autowiring hlášky za běhu
		if ($this->config->passkeyEnabled && $builder->getByType(PasskeyQueryFactory::class) === null) {
			throw new RuntimeException('fancyadmin: passkeyEnabled je zapnuté, ale v projektu chybí implementace ' . PasskeyQueryFactory::class . '. Vytvořte entitu Passkey, PasskeyQuery, PasskeyQueryFactory, PasskeyForm a PasskeyGrid podle README (sekce 19), nebo passkeys vypněte.');
		}

		// Bez známého rpId by WebAuthn ceremonie selhala až za běhu obecnou hláškou
		// "přihlašovací klíče nejsou dostupné". Pozdější změna rpId navíc zneplatní všechny
		// už registrované klíče, takže se vyplatí ho mít explicitně v konfiguraci.
		if ($this->config->passkeyEnabled
			&& ($this->config->passkeyRpId ?: PasskeyService::deriveRpId($this->config->adminHostPath)) === ''
		) {
			throw new RuntimeException('fancyadmin: passkeyEnabled je zapnuté, ale WebAuthn Relying Party ID není známé. Nastavte passkeyRpId na doménu adminu (bez schématu, cesty a portu), nebo doplňte adminHostPath. Pozor: pozdější změna rpId zneplatní všechny už registrované klíče.');
		}
	}

	public function afterCompile(ClassType $class): void
	{
		// Formuláře fancyadminu používají $form->addPasswordReveal(), což je extension
		// method - musí se zaregistrovat za běhu, jinak by ji každý projekt musel
		// registrovat sám ve svém Bootstrapu.
		$this->getInitialization()->addBody(PasswordRevealInput::class . '::register();');
	}

	private function validateTraitInterfaceCompliance(): void
	{
		$traitInterfaceMap = [
			AclResourceTrait::class => AclResource::class,
			AclRoleTrait::class => AclRole::class,
			IdentityTrait::class => Identity::class,
			IdentityPasskeysTrait::class => HasPasskeys::class,
			ProfileTrait::class => Profile::class,
		];

		$loader = new RobotLoader();
		$loader->addDirectory(__DIR__ . '/../../../../../app/Model/Entities');
		$loader->acceptFiles = ['*.php'];
		$loader->rebuild();

		foreach (array_keys($loader->getIndexedClasses()) as $class) {
			if (!class_exists($class)) {
				continue;
			}

			$reflection = new ReflectionClass($class);

			if (!$reflection->isInstantiable() || $reflection->isAbstract()) {
				continue;
			}

			$usedTraits = $this->class_uses_recursive($class);

			foreach ($traitInterfaceMap as $trait => $interface) {
				if (in_array($trait, $usedTraits, true) && !$reflection->implementsInterface($interface)) {
					throw new RuntimeException("Třída $class používá $trait, ale neimplementuje požadované rozhraní $interface.");
				}
			}
		}
	}

	private function class_uses_recursive(string $class): array
	{
		$results = [];

		do {
			$results += class_uses($class);
		} while ($class = get_parent_class($class));

		foreach ($results as $trait) {
			$results += $this->class_uses_recursive($trait);
		}

		return array_unique($results);
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}