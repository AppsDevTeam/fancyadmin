<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\DI;

use ADT\FancyAdmin\Console\CreateIdentityCommand;
use ADT\FancyAdmin\Console\GenerateMissingAclResourcesCommand;
use ADT\FancyAdmin\Console\MoveLogsCommand;
use ADT\FancyAdmin\Model\Log\LogMover;
use ADT\FancyAdmin\Console\PrintLogSchemaCommand;
use ADT\FancyAdmin\Console\PurgeLogsCommand;
use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Model\Audit\AuditActor;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Audit\ChangeLogAuditSubscriber;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use ADT\FancyAdmin\Model\Security\Authenticator;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\Model\Security\ReturnPath;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Model\Services\JsComponents;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\Forms\Controls\PasswordRevealInput;
use ADT\LogSanitizer\SensitiveDataSanitizer;
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
			'personalDataAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::PROFILE_PERSONAL_DATA),
			'context' => Expect::string()->default(null),
			'jsComponentsConfig' => Expect::array()->default([]),
			'locksDir' => Expect::string()->required(),
			// Retence logů pro fancyadmin:purge-logs. Pořadí rozhoduje, mazání jde
			// odshora dolů - záleží na něm tam, kde jsou tabulky svázané cizím klíčem.
			// audit_log sem NEPATŘÍ, ten odváží a maže mover.
			'purge' => Expect::listOf(Expect::structure([
				'entity' => Expect::string()->required(),
				// cokoliv, co bere DateTimeImmutable::modify(), např. '6 months'
				'retention' => Expect::string()->required(),
			])->castTo('array'))->default([]),
			// Odvoz logu do oddeleneho uloziste - viz fancyadmin:move-logs. Bez vyplneneho
			// spojeni a zdroje se command neregistruje, protoze nema kam vozit.
			// Zaznam si do cile veze sve id, takze cilova databaze patri VZDY jen jednomu
			// zdroji - dva by si id prepsaly.
			'logMover' => Expect::structure([
				// DBAL spojeni do cile, napr. @nettrine.dbal.connections.logdb.connection
				'connection' => Expect::string()->dynamic()->nullable()->default(null),
				// co se odvazi; `table` je nepovinna, vychozi je stejny nazev jako ve zdroji
				'tables' => Expect::listOf(Expect::structure([
					'entity' => Expect::string()->required(),
					'table' => Expect::string()->nullable()->default(null),
					// jen pro fancyadmin:print-log-schema, samotny odvoz je nepouziva:
					// `hot` = hranice provozni a archivni vrstvy (komprese v TimescaleDB),
					// `retention` = po jake dobe zaznam v cili zanikne
					'hot' => Expect::string()->nullable()->default(null),
					'retention' => Expect::string()->nullable()->default(null),
					// Podminka (SQL, bez WHERE) omezujici, co uz je zrale na odvoz. Patri sem
					// tabulka, do ktere se po zalozeni jeste zapisuje - odvezeny radek uz
					// aplikace ve zdroji nenajde a dopsat do nej nedokaze. Zaznamy, ktere se
					// nikdy nedokonci, je potreba pustit dal casem, jinak ve zdroji zustanou
					// navzdy: `response_at IS NOT NULL OR created_at < NOW() - INTERVAL 1 DAY`.
					'where' => Expect::string()->nullable()->default(null),
				])->castTo('array'))->default([]),
			]),
			'keycloakEnabled' => Expect::bool()->default(false),
			// Vypnutí validace TLS certifikátu Keycloak serveru — POUZE pro lokální vývoj (self-signed cert)
			'keycloakVerifySsl' => Expect::bool()->default(true),
			'passkeyEnabled' => Expect::bool()->default(false),
			// Záchranná cesta při vynuceném 2FA: jednorázový kód na e-mail místo tvrdé zdi (README 19.9)
			'passkeyEmailOtpEnabled' => Expect::bool()->default(true),
			// Zamknout uživatele přihlášeného jednorázovým kódem na Profil, dokud si nepřidá klíč
			'passkeyEnrollmentRequired' => Expect::bool()->default(false),
			// WebAuthn Relying Party ID (doména) — když není nastaveno, odvodí se za běhu host z adminHostPath
			'passkeyRpId' => Expect::string()->nullable()->default(null),
			// WebAuthn Relying Party name — když není nastaveno, použije se projectName
			'passkeyRpName' => Expect::string()->nullable()->default(null),
			// Hosty, na ktere smi mirit verejna URL SSO instance - viz Model\Security\SsoHostAllowlist.
			// Prazdny seznam nepousti nic, projekt se SSO si hosty vypsat musi.
			'ssoAllowedHosts' => Expect::listOf('string')->default([]),
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
				'personalDataAclResource' => $this->config->personalDataAclResource,
				'jsComponentsConfig' => $this->config->jsComponentsConfig,
				'context' => $this->config->context,
				'colors' => (array) $this->config->colors,
				'keycloakEnabled' => $this->config->keycloakEnabled,
				'passkeyEnabled' => $this->config->passkeyEnabled,
				'passkeyEmailOtpEnabled' => $this->config->passkeyEmailOtpEnabled,
				'passkeyEnrollmentRequired' => $this->config->passkeyEnrollmentRequired,
				'passkeyRpId' => $this->config->passkeyRpId,
				'passkeyRpName' => $this->config->passkeyRpName,
				'ssoAllowedHosts' => $this->config->ssoAllowedHosts,
			]);

		$builder->addDefinition($this->prefix('jsComponents'))
			->setFactory(JsComponents::class);

		$builder->addDefinition($this->prefix('passkeyService'))
			->setFactory(PasskeyService::class);

		// Cíl "kam po přihlášení" v cookie, aby nepřihlášenému nevznikala session
		$builder->addDefinition($this->prefix('returnPath'))
			->setFactory(ReturnPath::class);

		// Jednotny auditni stream (tabulka audit_log). Knihovny na fancyadminu
		// nezavisi ani o nem nevedi - v neonu se na jeho log() jen odkazou
		// callbackem, napr.:  exporter: auditLogger: [@fancyAdmin.auditLogger, log]
		$builder->addDefinition($this->prefix('auditLogger'))
			->setFactory(AuditLogger::class);

		$builder->addDefinition($this->prefix('auditActor'))
			->setFactory(AuditActor::class);

		// Zmeny entit s atributem #[Audited] -> audit_log. Navesit v projektu, aby
		// o tom rozhodoval ten, kdo zna poradi rozsireni:
		//   doctrineLoggable: onLogEntry: [[@fancyAdmin.changeLogAuditSubscriber, logEntry]]
		$builder->addDefinition($this->prefix('changeLogAuditSubscriber'))
			->setFactory(ChangeLogAuditSubscriber::class);


		// Keycloak — registrace KeycloakManager (instance se vytváří lazy z DB)
		if ($this->config->keycloakEnabled) {
			$builder->addDefinition($this->prefix('keycloakManager'))
				->setFactory(KeycloakManager::class)
				->setArgument('verifySsl', $this->config->keycloakVerifySsl);
		}

		// command registration

		$defs[] = $builder->addDefinition($this->prefix('createIdentity'))
			->setFactory(CreateIdentityCommand::class)
			->setAutowired(false);

		$defs[] = $builder->addDefinition($this->prefix('generateMissingAclResources'))
			->setFactory(GenerateMissingAclResourcesCommand::class, [
				'appDir' => $builder->parameters['appDir'],
			])
			->setAutowired(false);

		$defs[] = $builder->addDefinition($this->prefix('purgeLogs'))
			->setFactory(PurgeLogsCommand::class, ['config' => $this->config->purge])
			->setAutowired(false);

		$mover = $this->config->logMover;
		if ($mover->connection !== null || $mover->tables) {
			// Pulka nastaveni je horsi nez zadne: odvoz by bud nemel kam vozit, nebo by
			// nemel co. Radsi hlasita chyba pri kompilaci.
			if ($mover->connection === null || !$mover->tables) {
				throw new RuntimeException('fancyadmin: logMover potřebuje vyplnit connection i tables.');
			}

			// sluzba, ne jen command: odvoz se pousti i z fronty, aby jel po minutach
			$builder->addDefinition($this->prefix('logMover'))
				->setFactory(LogMover::class, [
					'targetConnection' => $mover->connection,
					'config' => $mover->tables,
				]);

			$defs[] = $builder->addDefinition($this->prefix('moveLogs'))
				->setFactory(MoveLogsCommand::class)
				->setAutowired(false);

			$defs[] = $builder->addDefinition($this->prefix('printLogSchema'))
				->setFactory(PrintLogSchemaCommand::class, [
					'targetConnection' => $mover->connection,
					'config' => $mover->tables,
				])
				->setAutowired(false);
		}

		foreach ($defs as $_def) {
			$_def->addSetup('setLocksDir', [$this->config->locksDir]);
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		// Sanitizer si projekty registruji samy, protoze si upravuji citliva pole
		// i chovani pri nalezu PANu. Auditni subscriber ho ale potrebuje vzdy,
		// takze projektu, ktery zadny nema, dame vychozi - jinak by mu kontejner
		// prestal jit zkompilovat jen tim, ze si aktualizoval fancyadmin.
		if ($builder->getByType(SensitiveDataSanitizer::class) === null) {
			$builder->addDefinition($this->prefix('logSanitizer'))
				->setFactory(SensitiveDataSanitizer::class);
		}
		$securityUserDef = $builder->getDefinitionByType(SecurityUser::class);
		$securityUserDef->addSetup('setFullDataAclResource', [$this->config->fullDataAclResource]);
		$securityUserDef->addSetup('setBackofficeAclResource', [$this->config->backofficeAclResource]);
		$securityUserDef->addSetup('setPersonalDataAclResource', [$this->config->personalDataAclResource]);

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

		// PasskeyService stojí na HasPasskeys (user handle + inverzní kolekce klíčů). Projekt,
		// který si kolekci passkeys namapoval ručně místo IdentityPasskeysTrait, projde
		// i orm:validate-schema a chyba se projeví až jako 500 při registraci prvního klíče.
		$appDir = $builder->parameters['appDir'] ?? null;

		if ($this->config->passkeyEnabled
			&& ($invalidIdentity = self::findIdentityWithoutPasskeys(
				self::findProjectEntityClasses(is_string($appDir) ? $appDir : null, Identity::class)
			)) !== null
		) {
			throw new RuntimeException('fancyadmin: passkeyEnabled je zapnuté, ale ' . $invalidIdentity . ' neimplementuje ' . HasPasskeys::class . '. Přidejte entitě `use IdentityPasskeysTrait` a `implements HasPasskeys` podle README (sekce 19), nebo passkeys vypněte.');
		}
	}

	/**
	 * @param class-string[] $identityClasses projektové entity Identity
	 * @return class-string|null první entita bez HasPasskeys, null když jsou všechny v pořádku
	 */
	public static function findIdentityWithoutPasskeys(array $identityClasses): ?string
	{
		foreach ($identityClasses as $_identityClass) {
			if (!is_a($_identityClass, HasPasskeys::class, true)) {
				return $_identityClass;
			}
		}

		return null;
	}

	/**
	 * Sken projektových entit v `%appDir%/Model/Entities` podle implementovaného rozhraní.
	 *
	 * Best-effort: když se appDir nepodaří určit nebo entity v konvenčním adresáři nejsou
	 * (dev checkout, path repository), vrátí prázdno a volající kontrola se přeskočí —
	 * nefunkční detekce cesty nesmí shodit kompilaci kontejneru. Přesně na tohle dojela
	 * a proto byla odstraněna dřívější validateTraitInterfaceCompliance() (42899d7), která
	 * si cestu k entitám skládala natvrdo relativně k tomuhle souboru.
	 *
	 * Spoléhá na to, že si projekt adresář autoloaduje (composer `autoload.psr-4` nad app/);
	 * třídy, které se nepodaří načíst, se tiše přeskočí.
	 *
	 * @param string|null $appDir hodnota parametru appDir, null když není k dispozici
	 * @param class-string $interface rozhraní, které musí entita implementovat
	 * @return class-string[] instancovatelné entity projektu implementující $interface
	 */
	public static function findProjectEntityClasses(?string $appDir, string $interface): array
	{
		if ($appDir === null || !is_dir($entitiesDir = $appDir . '/Model/Entities')) {
			return [];
		}

		$loader = new RobotLoader();
		$loader->addDirectory($entitiesDir);
		$loader->acceptFiles = ['*.php'];
		$loader->rebuild();

		$entityClasses = [];
		foreach (array_keys($loader->getIndexedClasses()) as $_class) {
			if (!class_exists($_class)) {
				continue;
			}

			$reflection = new ReflectionClass($_class);

			if ($reflection->isInstantiable() && $reflection->implementsInterface($interface)) {
				$entityClasses[] = $_class;
			}
		}

		return $entityClasses;
	}

	public function afterCompile(ClassType $class): void
	{
		// Formuláře fancyadminu používají $form->addPasswordReveal(), což je extension
		// method - musí se zaregistrovat za běhu, jinak by ji každý projekt musel
		// registrovat sám ve svém Bootstrapu.
		$this->getInitialization()->addBody(PasswordRevealInput::class . '::register();');
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}