<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\DI;

use ADT\AiChat\AgentProvisioner;
use ADT\AiChat\AiChatOptions;
use ADT\AiChat\ChatOptions;
use ADT\AiChat\ChatService;
use ADT\AiChat\Client\GuzzleManagedAgentsClient;
use ADT\AiChat\Dispatch\BackgroundQueueMessageDispatcher;
use ADT\AiChat\Http\ChatEndpoint;
use ADT\AiChat\Mcp\McpServer;
use ADT\AiChat\PiiResponseFilter;
use ADT\AiChat\PiiValueCollector;
use ADT\AiChat\Pseudonymization\OpenAiCompatiblePiiDetector;
use ADT\AiChat\Pseudonymization\Pseudonymizer;
use ADT\AiChat\SystemPromptBuilder;
use ADT\AiChat\ToolHandler;
use ADT\DoctrineAnonymization\AnonymizationPolicy;
use ADT\DoctrineAnonymization\AnonymizedViewsGenerator;
use ADT\DoctrineAnonymization\GenerateAnonymizedViewsCommand;
use ADT\DoctrineAnonymization\PersonalDataColumns;
use ADT\DoctrineAnonymization\ReadOnlyConnectionOptions;
use ADT\DoctrineAnonymization\ReadOnlyQueryExecutor;
use ADT\DoctrineAnonymization\SchemaDescriber;
use ADT\FancyAdmin\Console\CreateIdentityCommand;
use ADT\FancyAdmin\Console\GenerateMissingAclResourcesCommand;
use ADT\FancyAdmin\Console\ProvisionAiAgentCommand;
use ADT\FancyAdmin\Console\PurgeLogsCommand;
use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Core\SignalCsrfRouteList;
use ADT\FancyAdmin\Model\AiChat\AiChatTranslator;
use ADT\FancyAdmin\Model\AiChat\AiMcpEndpoint;
use ADT\FancyAdmin\Model\AiChat\AuditPiiCheckListener;
use ADT\FancyAdmin\Model\Audit\AuditActor;
use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Audit\ChangeLogAuditSubscriber;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use ADT\FancyAdmin\Model\Quickbar\QuickbarSettings;
use ADT\FancyAdmin\Model\Security\Authenticator;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Security\LastLoginSubscriber;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\Model\Security\ReturnPath;
use ADT\FancyAdmin\Model\Security\SecretCipher;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Model\Services\JsComponents;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\Forms\Controls\PasswordRevealInput;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use Contributte\Datagrid\Datagrid;
use Contributte\Translation\DI\TranslationProviderInterface;
use Doctrine\Migrations\DependencyFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Config\Loader;
use Nette\DI\Definitions\Statement;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Routing\Router;
use Nette\Schema\Schema;
use Nette\Security\Resource;
use ReflectionClass;
use RuntimeException;
use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;
use Tracy\ILogger;

class FancyAdminExtension extends CompilerExtension implements TranslationProviderInterface
{
	/** Viz getAiExcludedEntities(); neexistující třídy policy ignoruje. */
	public const array AI_DEFAULT_EXCLUDED_ENTITIES = [
		'ADT\\DoctrineLoggable\\Entity\\ChangeLog',
		'ADT\\DoctrineAuthenticator\\StorageEntity',
		'ADT\\DoctrineAuthenticator\\OTP\\OnetimeToken',
		'ADT\\DoctrineAuthenticator\\AuthLog',
		'ADT\\DoctrineAuthenticator\\LoginAttempt',
	];

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
			// Adresar (relativne k webrootu) s icon-192.png, icon-512.png a apple-touch-icon.png -
			// ikona a nazev aplikace pridane na plochu / domovskou obrazovku (web manifest)
			'appIconsDir' => Expect::string()->nullable()->default(null),
			'hmr' => Expect::bool()->default(false),
			'customerAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::CUSTOMER_DASHBOARD),
			'backofficeAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::BACKOFFICE_DASHBOARD),
			'fullDataAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::FULL_DATA),
			'personalDataAclResource' => Expect::type(Resource::class)->default(AclResourceNameEnum::PROFILE_PERSONAL_DATA),
			// Více účtů (tenantů) v jedné databázi - data se oddělují podle účtu a uživatel
			// si účet vybírá. false = jeden tenant na databázi (projekt bez účtů, nebo
			// s databází pro každého zákazníka): bez filtrů podle účtu, výběru účtu
			// a zákaznického portálu. Viz README, sekce 27 (Single-tenant režim).
			'multiTenant' => Expect::bool()->default(true),
			'context' => Expect::string()->default(null),
			'jsComponentsConfig' => Expect::array()->default([]),
			'locksDir' => Expect::string()->required(),
			// Retence logů pro fancyadmin:purge-logs. Pořadí rozhoduje, mazání jde
			// odshora dolů - záleží na něm tam, kde jsou tabulky svázané cizím klíčem.
			// audit_log sem NEPATŘÍ, ten odváží a maže mover (adt/log-mover).
			'purge' => Expect::listOf(Expect::structure([
				'entity' => Expect::string()->required(),
				// cokoliv, co bere DateTimeImmutable::modify(), např. '6 months'
				'retention' => Expect::string()->required(),
			])->castTo('array'))->default([]),
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
			// Klíč pro šifrování tajných hodnot v DB (konfigurace typu secret), typicky z env.
			// Bez něj nejde tajnou hodnotu uložit ani přečíst. README, Konfigurace - tajné hodnoty
			'secretKey' => Expect::string()->nullable()->default(null),
			// AI asistent nad daty projektu (adt/ai-chat), README, AI asistent
			'ai' => Expect::structure([
				'enabled' => Expect::bool()->default(false),
				'apiKey' => Expect::string()->nullable()->default(null),
				// služba implementující ADT\AiChat\Client\ApiKeyProvider (např. klíč z konfigurace
				// v DB pro každou instanci); má přednost před apiKey
				'apiKeyProvider' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable()->default(null),
				'model' => Expect::string()->default('claude-sonnet-5-5'),
				// projektové entity implementující AiConversation, AiMessage, AiAgent
				'conversationClass' => Expect::string()->nullable()->default(null),
				'messageClass' => Expect::string()->nullable()->default(null),
				'agentClass' => Expect::string()->nullable()->default(null),
				// prefix jména agenta v Anthropic konzoli; null = project
				'agentNamePrefix' => Expect::string()->nullable()->default(null),
				// read-only DB uživatel se SELECT jen nad anonymizovaným schématem:
				// user, password, dbname (výchozí `<db>_anon`), host, port, maxRows, ...
				'readOnlyConnection' => Expect::array()->default([]),
				// další entity, které nemají v anonymizovaném schématu žádný pohled
				// (přidávají se k AI_DEFAULT_EXCLUDED_ENTITIES a k ai.mcp.tokenClass)
				'excludedEntities' => Expect::listOf('string')->default([]),
				// null = výchozí šablona z adt/ai-chat
				'systemPromptPath' => Expect::string()->nullable()->default(null),
				'exportEnabled' => Expect::bool()->default(true),
				// PSR logger pro chyby tahů (např. '@monolog.logger'); null = Tracy logger přes PSR adaptér.
				// Záměrně se neautowiruje - v projektu bývá LoggerInterface i SQL logger Doctrine.
				'logger' => Expect::string()->nullable()->default(null),
				// jméno callbacku v backgroundQueue.callbacks
				'queueCallback' => Expect::string()->default(BackgroundQueueMessageDispatcher::DEFAULT_CALLBACK),
				// další klíče ChatOptions: rateLimitPerMinute, rateLimitPerHour, attachments, ...
				'chat' => Expect::array()->default([]),
				// texty, kterými se v odpovědích nahrazují osobní údaje (klíče PiiResponseFilter)
				'piiLabels' => Expect::arrayOf('string', 'string')->default([
					'secret' => '[odstraněno: tajný údaj]',
					'email' => '[odstraněný e-mail]',
					'bank_account' => '[odstraněné číslo účtu]',
					'phone' => '[odstraněné telefonní číslo]',
					'value' => '[odstraněný osobní údaj]',
				]),
				// Pseudonymizace textu uživatele před odesláním do AI (README, AI asistent - Pseudonymizace vstupu)
				'pseudonymization' => Expect::structure([
					'enabled' => Expect::bool()->default(false),
					// bez kontroly LLM detektorem text do AI neodejde (fail-closed)
					'required' => Expect::bool()->default(true),
					// projektová entita implementující AiPseudonym (povinná při enabled)
					'pseudonymClass' => Expect::string()->nullable()->default(null),
					// služba implementující ADT\AiChat\Pseudonymization\PiiSubjectResolver
					'resolver' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable()->default(null),
					// formát kotvy subjektu s %s (např. '[PACIENT #%%s]'); null = výchozí ai-chat
					'subjectAnchor' => Expect::string()->nullable()->default(null),
					// druh údaje => název kotvy (name: OSOBA, …); prázdné = výchozí ai-chat
					'labels' => Expect::arrayOf('string', 'string')->default([]),
					// poznámka pro AI na konci textu; null = výchozí ai-chat
					'note' => Expect::string()->nullable()->default(null),
					// OpenAI-kompatibilní LLM (qwen); bez url jen regulární výrazy a resolver
					'detector' => Expect::structure([
						'url' => Expect::string()->nullable()->default(null),
						'model' => Expect::string()->nullable()->default(null),
						'apiKey' => Expect::string()->nullable()->default(null),
						'authUser' => Expect::string()->nullable()->default(null),
						'authPassword' => Expect::string()->nullable()->default(null),
						'timeout' => Expect::int()->default(20),
						// null = výchozí anglický prompt ai-chat
						'systemPrompt' => Expect::string()->nullable()->default(null),
					]),
				]),
				// MCP server nad stejnými nástroji pro jiné AI agenty (README, AI asistent - MCP server)
				'mcp' => Expect::structure([
					'enabled' => Expect::bool()->default(false),
					// projektová entita implementující AiMcpToken
					'tokenClass' => Expect::string()->nullable()->default(null),
					// jméno serveru, které klient uvidí; null = project
					'serverName' => Expect::string()->nullable()->default(null),
					// instrukce pro model klienta; null = výchozí z adt/ai-chat (McpServer::DEFAULT_INSTRUCTIONS)
					'instructions' => Expect::string()->nullable()->default(null),
				]),
			]),
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
			// Barvy pro tmavý režim (html[data-bs-theme=dark]) - stejné klíče jako colors.
			// Vše nepovinné: bez hodnoty platí výchozí tmavá paleta z _variables.scss,
			// případně barva ze světlého režimu (typicky primaryColor).
			'darkColors' => Expect::structure(array_fill_keys([
				'backgroundColor', 'dashboardAccentColor', 'primaryColor', 'primaryColorDark', 'primaryColorDark20',
				'secondaryColor', 'secondaryColorDark', 'secondaryColorDarker', 'ternaryColor', 'ternaryTextColor',
				'loginBackground', 'loginInputTextColor', 'loginBackgroundInput', 'loginBackgroundInputFocus',
				'inputBorder', 'inputFocusBorder', 'inputFocusBackground',
				'sidePanelItemColor', 'textColor', 'loginPageBackground', 'loginPageTextColor',
			], Expect::string()->nullable()->default(null))),
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
				'appIconsDir' => $this->config->appIconsDir,
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
				'multiTenant' => $this->config->multiTenant,
				'aiEnabled' => $this->config->ai->enabled,
				'jsComponentsConfig' => $this->config->jsComponentsConfig,
				'context' => $this->config->context,
				'colors' => (array) $this->config->colors,
				'darkColors' => array_filter((array) $this->config->darkColors, fn(?string $color) => $color !== null),
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

		$builder->addDefinition($this->prefix('quickbarSettings'))
			->setFactory(QuickbarSettings::class);

		$builder->addDefinition($this->prefix('passkeyService'))
			->setFactory(PasskeyService::class);

		$builder->addDefinition($this->prefix('secretCipher'))
			->setFactory(SecretCipher::class, ['secretKey' => $this->config->secretKey]);

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

		// Razitko posledniho prihlaseni na identite - navesuje se samo, viz beforeCompile.
		// Prepinac k tomu zadny neni zamerne: sloupec `last_login_at` je soucast
		// IdentityTrait, takze ho Doctrine vybira v kazdem dotazu na identitu a projekt,
		// ktery po aktualizaci baliku nespusti migraci, je rozbity i bez tohohle zapisu.
		$builder->addDefinition($this->prefix('lastLoginSubscriber'))
			->setFactory(LastLoginSubscriber::class);


		// Keycloak — registrace KeycloakManager (instance se vytváří lazy z DB)
		if ($this->config->keycloakEnabled) {
			$builder->addDefinition($this->prefix('keycloakManager'))
				->setFactory(KeycloakManager::class)
				->setArgument('verifySsl', $this->config->keycloakVerifySsl);
		}

		if ($this->config->ai->enabled) {
			$this->loadAiConfiguration();
		}

		// command registration

		$defs[] = $builder->addDefinition($this->prefix('createIdentity'))
			->setFactory(CreateIdentityCommand::class)
			->setAutowired(false);

		$defs[] = $builder->addDefinition($this->prefix('generateMissingAclResources'))
			->setFactory(GenerateMissingAclResourcesCommand::class, [
				'appDir' => $builder->parameters['appDir'],
				// zapoji se v beforeCompile(), az budou registrovane sluzby ostatnich rozsireni
				'migrations' => null,
			])
			->setAutowired(false);

		$defs[] = $builder->addDefinition($this->prefix('purgeLogs'))
			->setFactory(PurgeLogsCommand::class, ['config' => $this->config->purge])
			->setAutowired(false);

		foreach ($defs as $_def) {
			$_def->addSetup('setLocksDir', [$this->config->locksDir]);
		}
	}

	/**
	 * Služby adt/ai-chat. Anonymizační služby (adt/doctrine-anonymization) se doplní
	 * až v beforeCompile, jen pokud si je projekt neregistruje sám.
	 */
	private function loadAiConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$ai = $this->config->ai;

		// apiKey záměrně není povinný - typicky jde z env a vývojář bez klíče má mít funkční
		// aplikaci; volání API pak skončí chybou autorizace, kterou widget zobrazí
		foreach (['conversationClass', 'messageClass', 'agentClass'] as $_key) {
			if (empty($ai->$_key)) {
				throw new RuntimeException('fancyadmin: ai.enabled je zapnuté, ale chybí ai.' . $_key . '. Viz README (AI asistent).');
			}
		}

		$builder->addDefinition($this->prefix('ai.client'))
			->setFactory(GuzzleManagedAgentsClient::class, [
				'apiKey' => $ai->apiKeyProvider ?? (string) $ai->apiKey,
				'model' => $ai->model,
			]);

		$builder->addDefinition($this->prefix('ai.toolHandler'))
			->setFactory(ToolHandler::class, ['exportEnabled' => $ai->exportEnabled]);

		$builder->addDefinition($this->prefix('ai.piiValueCollector'))
			->setFactory(PiiValueCollector::class);

		$builder->addDefinition($this->prefix('ai.piiResponseFilter'))
			->setFactory(PiiResponseFilter::class, ['labels' => $ai->piiLabels]);

		$builder->addDefinition($this->prefix('ai.turnOptions'))
			->setFactory(AiChatOptions::class);

		$builder->addDefinition($this->prefix('ai.systemPromptBuilder'))
			->setFactory(SystemPromptBuilder::class, [
				'templatePath' => $ai->systemPromptPath ?? SystemPromptBuilder::defaultTemplatePath(),
			]);

		$builder->addDefinition($this->prefix('ai.agentProvisioner'))
			->setFactory(AgentProvisioner::class, [
				'agentClass' => $ai->agentClass,
				'namePrefix' => $ai->agentNamePrefix ?? $this->config->project ?? 'fancyadmin',
			]);

		$builder->addDefinition($this->prefix('ai.chatOptions'))
			->setFactory(ChatOptions::class . '::fromArray', [[
				'conversationClass' => $ai->conversationClass,
				'messageClass' => $ai->messageClass,
				'ownerField' => 'identity',
				'senderField' => 'sender',
			] + self::getAiChatOptions($ai->chat, $ai->pseudonymization->enabled)]);

		$builder->addDefinition($this->prefix('ai.dispatcher'))
			->setFactory(BackgroundQueueMessageDispatcher::class, ['callbackName' => $ai->queueCallback]);

		// projekt na ni věší callback fronty: aiChatProcessMessage: [@<název rozšíření>.ai.chatService, processQueuedMessage]
		$chatService = $builder->addDefinition($this->prefix('ai.chatService'))
			->setFactory(ChatService::class)
			->addSetup('setTranslator', [[$this->prefix('@ai.translator'), 'translate']]);

		// Bez pseudonymizace výslovně null - jinak by autowiring sáhl po případné službě projektu
		$chatService->setArgument('pseudonymizer', null)->setArgument('piiCheckListener', null);
		if ($ai->pseudonymization->enabled) {
			$this->loadAiPseudonymization();
			$chatService
				->setArgument('pseudonymizer', $this->prefix('@ai.pseudonymizer'))
				->setArgument('piiCheckListener', $this->prefix('@ai.piiCheckListener'));
		}

		$builder->addDefinition($this->prefix('ai.endpoint'))
			->setFactory(ChatEndpoint::class);

		$builder->addDefinition($this->prefix('ai.translator'))
			->setFactory(AiChatTranslator::class);

		if ($ai->mcp->enabled) {
			if (empty($ai->mcp->tokenClass)) {
				throw new RuntimeException('fancyadmin: ai.mcp.enabled je zapnuté, ale chybí ai.mcp.tokenClass. Viz README (AI asistent - MCP server).');
			}

			$builder->addDefinition($this->prefix('ai.mcpServer'))
				->setFactory(McpServer::class, [
					'tools' => $this->prefix('@ai.toolHandler'),
					'responseFilter' => $this->prefix('@ai.piiResponseFilter'),
					'serverName' => $ai->mcp->serverName ?? $this->config->project ?? 'fancyadmin',
					'instructions' => $ai->mcp->instructions ?? McpServer::DEFAULT_INSTRUCTIONS,
				]);

			$builder->addDefinition($this->prefix('ai.mcpEndpoint'))
				->setFactory(AiMcpEndpoint::class, ['tokenClass' => $ai->mcp->tokenClass]);
		}

		$builder->addDefinition($this->prefix('ai.provisionAgentCommand'))
			->setFactory(ProvisionAiAgentCommand::class)
			->addSetup('setLocksDir', [$this->config->locksDir])
			->setAutowired(false);
	}

	/**
	 * Pseudonymizace textu uživatele (adt/ai-chat Pseudonymizer): detektor qwen, kotvy
	 * v projektové entitě, audit ai_pii_check.
	 */
	private function loadAiPseudonymization(): void
	{
		$builder = $this->getContainerBuilder();
		$pseudonymization = $this->config->ai->pseudonymization;
		$detector = $pseudonymization->detector;

		if (empty($pseudonymization->pseudonymClass)) {
			throw new RuntimeException('fancyadmin: ai.pseudonymization.enabled je zapnuté, ale chybí ai.pseudonymization.pseudonymClass. Viz README (AI asistent - Pseudonymizace vstupu).');
		}

		// Registruje se i bez url - s required pak každou zprávu zastaví, bez něj vrátí null
		$builder->addDefinition($this->prefix('ai.piiDetector'))
			->setFactory(OpenAiCompatiblePiiDetector::class, [
				'url' => $detector->url,
				'model' => $detector->model,
				'apiKey' => $detector->apiKey,
				'authUser' => $detector->authUser,
				'authPassword' => $detector->authPassword,
				'required' => $pseudonymization->required,
				'timeout' => $detector->timeout,
				'systemPrompt' => $detector->systemPrompt,
			])
			->setAutowired(false);

		$resolver = $pseudonymization->resolver;
		$builder->addDefinition($this->prefix('ai.pseudonymizer'))
			->setFactory(Pseudonymizer::class, array_filter([
				'pseudonymClass' => $pseudonymization->pseudonymClass,
				'detector' => $this->prefix('@ai.piiDetector'),
				'resolver' => is_string($resolver) && !str_starts_with($resolver, '@') ? new Statement($resolver) : $resolver,
				'subjectAnchor' => $pseudonymization->subjectAnchor,
				'labels' => $pseudonymization->labels,
				'note' => $pseudonymization->note,
			], static fn($_value): bool => $_value !== null && $_value !== []) + ['resolver' => null]);

		$builder->addDefinition($this->prefix('ai.piiCheckListener'))
			->setFactory(AuditPiiCheckListener::class);
	}

	/**
	 * Klíče ChatOptions z konfigurace ai.chat. Přílohy se nahrávají do Anthropic Files API už
	 * ve webovém requestu a pseudonymizace na ně nedosáhne - při zapnuté pseudonymizaci jsou
	 * proto vypnuté, pokud je projekt výslovně nepovolí.
	 *
	 * @param array<string, mixed> $chat
	 * @return array<string, mixed>
	 */
	private static function getAiChatOptions(array $chat, bool $pseudonymization): array
	{
		if ($pseudonymization && !isset($chat['attachments']['maxCount'])) {
			$chat['attachments'] = ['maxCount' => 0] + ($chat['attachments'] ?? []);
		}

		return $chat;
	}

	/**
	 * Logger služeb AI se předává explicitně: autowiring LoggerInterface by vzal první
	 * PSR logger v kontejneru, a tím bývá SQL logger Doctrine, který na běžný error()
	 * spadne. Řeší se až v beforeCompile(), kdy už je zaregistrovaný Tracy.
	 */
	private function wireAiLogger(): void
	{
		$builder = $this->getContainerBuilder();
		$logger = $this->config->ai->logger;

		if ($logger === null && ($tracyLogger = $builder->getByType(ILogger::class)) !== null) {
			$logger = new Statement(TracyToPsrLoggerAdapter::class, ['@' . $tracyLogger]);
		}

		foreach (['ai.chatService', 'ai.endpoint', 'ai.mcpServer', 'ai.piiDetector'] as $_name) {
			if ($builder->hasDefinition($this->prefix($_name))) {
				$builder->getDefinition($this->prefix($_name))->setArgument('logger', $logger);
			}
		}
	}

	/**
	 * Entity vyřazené z anonymizovaného schématu - tabulky, které nejde maskovat po
	 * sloupcích a neregistrovaný čtenář pohledů _anon (AI, MCP klient) je vidět nesmí:
	 * změnový log (JSON se starými i novými hodnotami všech logovaných entit včetně jmen
	 * a hesel/klíčů před zašifrováním), sessions a jednorázové tokeny (převzetí účtu),
	 * přihlašovací logy (IP, uživatelská jména), tokeny MCP a kotvy pseudonymizace
	 * (skutečné osobní údaje z textu uživatele).
	 *
	 * @return list<class-string>
	 */
	private function getAiExcludedEntities(): array
	{
		$ai = $this->config->ai;

		return array_values(array_unique(array_merge(
			self::AI_DEFAULT_EXCLUDED_ENTITIES,
			$ai->mcp->tokenClass !== null ? [$ai->mcp->tokenClass] : [],
			$ai->pseudonymization->pseudonymClass !== null ? [$ai->pseudonymization->pseudonymClass] : [],
			$ai->excludedEntities,
		)));
	}

	/** Anonymizační služby, které si projekt neregistroval sám. */
	private function completeAiAnonymizationServices(): void
	{
		$builder = $this->getContainerBuilder();
		$ai = $this->config->ai;

		$services = [
			'policy' => [AnonymizationPolicy::class, ['excludedEntities' => $this->getAiExcludedEntities()]],
			'schemaDescriber' => [SchemaDescriber::class, []],
			'personalDataColumns' => [PersonalDataColumns::class, []],
			'viewsGenerator' => [AnonymizedViewsGenerator::class, []],
			'readOnlyConnectionOptions' => [ReadOnlyConnectionOptions::class . '::fromArray', [$ai->readOnlyConnection]],
			'readOnlyQueryExecutor' => [ReadOnlyQueryExecutor::class, []],
		];

		foreach ($services as $_name => [$_factory, $_args]) {
			$type = explode('::', $_factory)[0];
			if ($builder->getByType($type) === null) {
				$builder->addDefinition($this->prefix('ai.anonymization.' . $_name))
					->setType($type)
					->setFactory($_factory, $_args);
			}
		}

		if ($builder->findByType(GenerateAnonymizedViewsCommand::class) === []) {
			$builder->addDefinition($this->prefix('ai.anonymization.generateViewsCommand'))
				->setFactory(GenerateAnonymizedViewsCommand::class)
				->setAutowired(false);
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$this->checkSignalCsrfRouter();

		if ($this->config->ai->enabled) {
			$this->completeAiAnonymizationServices();
			$this->wireAiLogger();
		}

		// Doctrine Migrations je volitelne - kdyz ho projekt ma, rekne prikazu, kam migraci zapsat.
		if (
			class_exists(DependencyFactory::class)
			&& ($migrations = $builder->getByType(DependencyFactory::class)) !== null
		) {
			$builder->getDefinition($this->prefix('generateMissingAclResources'))
				->setArgument('migrations', '@' . $migrations);
		}

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
		// Query objekty znají jen SecurityUser - podle něj se rozhodují, jestli filtrovat podle účtu
		$securityUserDef->addSetup('setMultiTenant', [$this->config->multiTenant]);

		$authenticatorDef = $builder->getDefinitionByType(Authenticator::class);
		$authenticatorDef->addSetup('setFancyAdmin', [$this->prefix('@administration')]);
		$authenticatorDef->addSetup('setExpirationCallback', [
			new Statement('Closure::fromCallable', ['@fancyadmin.sessionExpirationCallback']),
		]);

		if ($this->config->keycloakEnabled) {
			$fancyAdminDef = $builder->getDefinition($this->prefix('administration'));
			$fancyAdminDef->addSetup('setKeycloakManager', [$this->prefix('@keycloakManager')]);
		}

		// Razitko posledniho prihlaseni visi na autentizacni udalosti, protoze cest dovnitr
		// ma projekt vic (administrace, mobilni aplikace, odkaz s jednorazovym tokenem, SSO)
		// a udalost je jedine misto, kudy projdou vsechny. Navesuje se tady, aby to projekt
		// nemusel opisovat do neonu.
		//
		// Bez autentikatoru se nic nedeje: kde se neni kam prihlasit, neni co razitkovat -
		// padat kvuli tomu pri kompilaci by projekt trestalo za neco, co neudelal.
		if (($authenticator = $builder->getByType(DoctrineAuthenticator::class)) !== null) {
			$builder->getDefinition($authenticator)
				->addSetup('$onAuthEvent[]', [[$this->prefix('@lastLoginSubscriber'), 'authEvent']]);
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
	 * CSRF token signálů nemá končit v adresách, kde se neověřuje — kořenový router projektu
	 * proto musí být {@see SignalCsrfRouteList}, který ho z nich odklidí.
	 *
	 * Hlídá se to při kompilaci kontejneru, protože jinak je to tichá chyba: aplikace běží dál,
	 * jen se token vleče každou adresou — adresním řádkem, historií prohlížeče, hlavičkou
	 * `Referer` i logy serveru. Router se zvenku neobaluje schválně: knihovny (například
	 * `adt/utils` v ErrorPresenteru) i Tracy si strom rout procházejí a obal by jim ho schoval.
	 */
	private function checkSignalCsrfRouter(): void
	{
		$builder = $this->getContainerBuilder();
		$name = $builder->getByType(Router::class);

		if ($name === null) {
			return;
		}

		$type = $builder->getDefinition($name)->getType();

		if ($type !== null && !is_a($type, SignalCsrfRouteList::class, true)) {
			throw new RuntimeException('fancyadmin: kořenový router projektu musí být ' . SignalCsrfRouteList::class . ', teď je to ' . $type . '. V RouterFactory::create() vraťte `new ' . SignalCsrfRouteList::class . '()` místo RouteListu — jinak CSRF token signálů skončí v každé vygenerované adrese. Viz README (sekce 14, CSRF ochrana signálů).');
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

		// Datagrid má výchozí solid ('fas fa-'), UI fancyadminu používá obrysové ikony
		$this->getInitialization()->addBody(Datagrid::class . '::$iconPrefix = ?;', ['fa-regular fa-']);
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}