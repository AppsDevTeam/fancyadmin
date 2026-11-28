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
use ADT\AiChat\Mcp\McpEndpoint;
use ADT\AiChat\Mcp\McpServer;
use ADT\AiChat\Mcp\McpTokenAuthenticator;
use ADT\AiChat\Tool\CompositeToolExecutor;
use ADT\AiChat\Skill\GetSkillTool;
use ADT\AiChat\Skill\ListSkillsTool;
use ADT\AiChat\Tool\Tool;
use ADT\AiChat\Tool\ToolProvider;
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
use ADT\Exporter\Model\Service\Exporter;
use ADT\FancyAdmin\Console\CreateIdentityCommand;
use ADT\FancyAdmin\Console\GenerateMissingAclResourcesCommand;
use ADT\FancyAdmin\Console\ProvisionAiAgentCommand;
use ADT\FancyAdmin\Console\PurgeLogsCommand;
use ADT\FancyAdmin\Core\FancyAdminRouter;
use ADT\FancyAdmin\Core\SignalCsrfRouteList;
use ADT\FancyAdmin\Model\AiChat\Page\AiPage;
use ADT\FancyAdmin\Model\AiChat\Page\OpenPageTool;
use ADT\FancyAdmin\Model\AiChat\AiChatTranslator;
use ADT\FancyAdmin\Model\AiChat\AiMcpEndpoint;
use ADT\FancyAdmin\Model\AiChat\AiMcpTokenPolicy;
use ADT\FancyAdmin\Model\Grid\DoctrineGridColumnSettingsStorage;
use ADT\FancyAdmin\Model\Grid\DoctrineGridPresetStorage;
use ADT\FancyAdmin\Model\Grid\Export\GridExport;
use ADT\FancyAdmin\Model\Grid\Export\GridExportCsvGenerator;
use ADT\FancyAdmin\Model\Grid\Export\GridExportOwner;
use ADT\FancyAdmin\Model\Grid\Export\GridExports;
use ADT\FancyAdmin\Model\Grid\Export\GridExportXlsxGenerator;
use ADT\FancyAdmin\Model\Tablet\DoctrineTabletSignRequests;
use ADT\FancyAdmin\Model\Tablet\TabletService;
use ADT\FancyAdmin\Model\Tablet\TabletSignAuditRenderer;
use ADT\FancyAdmin\Model\Tablet\TabletSigning;
use ADT\FancyAdmin\Model\WebPush\WebPushSender;
use ADT\FancyAdmin\Model\AiChat\AuditPiiCheckListener;
use ADT\FancyAdmin\Model\Audit\AuditActor;
use ADT\DoctrineAuthenticator\DoctrineAuthenticator;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Audit\ChangeLogAuditSubscriber;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use ADT\FancyAdmin\Model\Entities;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Defaults;
use ADT\FancyAdmin\Model\Queries\Defaults\DefaultBaseQuery;
use ADT\FancyAdmin\Model\Queries\Factories;
use ADT\FancyAdmin\UI\Components\Forms\Defaults as DefaultForms;
use ADT\FancyAdmin\UI\Components\Grids\Defaults as DefaultGrids;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use ADT\FancyAdmin\Model\Quickbar\QuickbarSettings;
use ADT\FancyAdmin\Model\Security\Authenticator;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakManager;
use ADT\FancyAdmin\Model\Push\NullPush;
use ADT\FancyAdmin\Model\Push\Push;
use ADT\FancyAdmin\Model\Security\LastLoginSubscriber;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\Model\Security\ReturnPath;
use ADT\FancyAdmin\Model\Security\SecretCipher;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Model\Services\JsComponents;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControl;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Forms\BiometricSignature\BiometricSignatureInput;
use ADT\Forms\Controls\PasswordRevealInput;
use ADT\BiometricSignature\BiometricSigning;
use ADT\BiometricSignature\Evidence\EvidenceVerifier;
use ADT\BiometricSignature\SigningPolicy;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use Contributte\Translation\DI\TranslationProviderInterface;
use Doctrine\Migrations\DependencyFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Extensions\InjectExtension;
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
	/** Výchozí české kotvy pseudonymizace (ai.pseudonymization.labels), projekt je může přepsat */
	public const array AI_PSEUDONYMIZATION_LABELS = [
		'name' => 'OSOBA',
		'birth_number' => 'RČ',
		'insurance_number' => 'POJIŠTĚNEC',
		'file_number' => 'KARTA',
		'phone' => 'TELEFON',
		'email' => 'EMAIL',
		'address' => 'ADRESA',
		'birth_date' => 'NAROZENÍ',
		'bank_account' => 'ÚČET',
		'document_number' => 'DOKLAD',
		'other' => 'ÚDAJ',
	];

	/** Výchozí poznámka pro AI (ai.pseudonymization.note), %1$s = ukázková kotva subjektu */
	public const string AI_PSEUDONYMIZATION_NOTE = '[Poznámka systému: osobní údaje v textu jsou nahrazené kotvami. '
		. '%1$s je záznam s ID 123 - toto ID používej v SQL i v nástrojích a kotvu piš i v odpovědi. '
		. '[OSOBA_1], [RČ_1], [TELEFON_1], [ADRESA_1] a další kotvy ve tvaru [DRUH_číslo] jsou skryté údaje, '
		. 'které systém nepřiřadil jednomu záznamu - kotvu piš i v odpovědi, uživatel místo ní uvidí původní údaj; '
		. 'když údaj pro úlohu potřebuješ, zeptej se uživatele na upřesnění.]';

	/** Výchozí řádek ke kandidátům (ai.pseudonymization.candidatesNote), %1$s = kotva, %2$s = kotvy kandidátů */
	public const string AI_PSEUDONYMIZATION_CANDIDATES_NOTE = '[Poznámka systému: %1$s může být jeden ze záznamů %2$s - '
		. 'když se úloha týká jednoho z nich, zeptej se uživatele, který z nich myslí, a nabídni je jako kotvy '
		. '(uživatel místo nich uvidí jména).]';

	public const array AI_DEFAULT_EXCLUDED_ENTITIES = [
		'ADT\\DoctrineLoggable\\Entity\\ChangeLog',
		'ADT\\DoctrineAuthenticator\\StorageEntity',
		'ADT\\DoctrineAuthenticator\\OTP\\OnetimeToken',
		'ADT\\DoctrineAuthenticator\\AuthLog',
		'ADT\\DoctrineAuthenticator\\LoginAttempt',
	];

	/**
	 * Výchozí query balíčku: továrna => [výchozí třída, rozhraní entity]. Zaregistrují se jen tam,
	 * kde projekt vlastní továrnu nemá, a jen když v projektu existuje právě jedna entita s tím
	 * rozhraním (projekt bez entity danou funkci nepoužívá). null = entitu určuje trait query.
	 */
	public const array DEFAULT_QUERIES = [
		Factories\AccountQueryFactory::class => [Defaults\AccountQuery::class, Entities\Account::class],
		Factories\AclRoleQueryFactory::class => [Defaults\AclRoleQuery::class, Entities\AclRole::class],
		Factories\AiConversationQueryFactory::class => [Defaults\AiConversationQuery::class, Entities\AiConversation::class],
		Factories\AiMcpTokenQueryFactory::class => [Defaults\AiMcpTokenQuery::class, Entities\AiMcpToken::class],
		Factories\ApiKeyQueryFactory::class => [Defaults\ApiKeyQuery::class, Entities\ApiKey::class],
		Factories\AuthLogQueryFactory::class => [Defaults\AuthLogQuery::class, null],
		Factories\ChangeLogQueryFactory::class => [Defaults\ChangeLogQuery::class, null],
		Factories\ConfigurationQueryFactory::class => [Defaults\ConfigurationQuery::class, Entities\Configuration::class],
		\ADT\Datagrid\Model\Queries\GridFilterQueryFactory::class => [Defaults\GridFilterQuery::class, Entities\GridFilter::class],
		Factories\IdentityQueryFactory::class => [Defaults\IdentityQuery::class, Entities\Identity::class],
		Factories\PasskeyQueryFactory::class => [Defaults\PasskeyQuery::class, Entities\Passkey::class],
		Factories\ProfileQueryFactory::class => [Defaults\ProfileQuery::class, Entities\Profile::class],
		Factories\SsoQueryFactory::class => [Defaults\SsoQuery::class, Entities\Sso::class],
		Factories\TabletQueryFactory::class => [Defaults\TabletQuery::class, Entities\Tablet::class],
	];

	/**
	 * Výchozí formuláře a gridy balíčku: továrna => [výchozí třída, rozhraní entity, bez které
	 * je projekt nepoužívá]. Zaregistrují se jen tam, kde projekt vlastní továrnu nemá.
	 */
	public const array DEFAULT_COMPONENTS = [
		\ADT\FancyAdmin\UI\Components\Forms\Account\AccountFormFactory::class => [DefaultForms\AccountForm::class, Entities\Account::class],
		\ADT\FancyAdmin\UI\Components\Forms\AclRole\AclRoleFormFactory::class => [DefaultForms\AclRoleForm::class, Entities\AclRole::class],
		\ADT\FancyAdmin\UI\Components\Forms\AiMcpToken\AiMcpTokenFormFactory::class => [DefaultForms\AiMcpTokenForm::class, Entities\AiMcpToken::class],
		\ADT\FancyAdmin\UI\Components\Forms\ApiKey\ApiKeyFormFactory::class => [DefaultForms\ApiKeyForm::class, Entities\ApiKey::class],
		\ADT\FancyAdmin\UI\Components\Forms\ChangePassword\ChangePasswordFormFactory::class => [DefaultForms\ChangePasswordForm::class, Entities\Identity::class],
		\ADT\FancyAdmin\UI\Components\Forms\Configuration\ConfigurationFormFactory::class => [DefaultForms\ConfigurationForm::class, Entities\Configuration::class],
		\ADT\FancyAdmin\UI\Components\Forms\Identity\IdentityFormFactory::class => [DefaultForms\IdentityForm::class, Entities\Identity::class],
		\ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordFormFactory::class => [DefaultForms\LostPasswordForm::class, Entities\Identity::class],
		\ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordFormFactory::class => [DefaultForms\NewPasswordForm::class, Entities\Identity::class],
		\ADT\FancyAdmin\UI\Components\Forms\Passkey\PasskeyFormFactory::class => [DefaultForms\PasskeyForm::class, Entities\Passkey::class],
		\ADT\FancyAdmin\UI\Components\Forms\PersonalData\PersonalDataFormFactory::class => [DefaultForms\PersonalDataForm::class, Entities\Profile::class],
		\ADT\FancyAdmin\UI\Components\Forms\Profile\ProfileFormFactory::class => [DefaultForms\ProfileForm::class, Entities\Profile::class],
		\ADT\FancyAdmin\UI\Components\Forms\SelectAccount\SelectAccountFormFactory::class => [DefaultForms\SelectAccountForm::class, Entities\Account::class],
		\ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormFactory::class => [DefaultForms\SignInForm::class, Entities\Identity::class],
		\ADT\FancyAdmin\UI\Components\Forms\Sso\SsoFormFactory::class => [DefaultForms\SsoForm::class, Entities\Sso::class],
		\ADT\FancyAdmin\UI\Components\Forms\Tablet\TabletFormFactory::class => [DefaultForms\TabletForm::class, Entities\Tablet::class],
		\ADT\FancyAdmin\UI\Components\Forms\TwoFactor\TwoFactorFormFactory::class => [DefaultForms\TwoFactorForm::class, Entities\Identity::class],
		\ADT\Datagrid\Component\GridFilter\GridFilterFormFactory::class => [DefaultForms\GridFilterForm::class, Entities\GridFilter::class],
		\ADT\FancyAdmin\UI\Components\Grids\Account\AccountGridFactory::class => [DefaultGrids\AccountGrid::class, Entities\Account::class],
		\ADT\FancyAdmin\UI\Components\Grids\AclRole\AclRoleGridFactory::class => [DefaultGrids\AclRoleGrid::class, Entities\AclRole::class],
		\ADT\FancyAdmin\UI\Components\Grids\AiConversation\AiConversationGridFactory::class => [DefaultGrids\AiConversationGrid::class, Entities\AiConversation::class],
		\ADT\FancyAdmin\UI\Components\Grids\AiMcpToken\AiMcpTokenGridFactory::class => [DefaultGrids\AiMcpTokenGrid::class, Entities\AiMcpToken::class],
		\ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGridFactory::class => [DefaultGrids\ApiKeyGrid::class, Entities\ApiKey::class],
		\ADT\FancyAdmin\UI\Components\Grids\AuthLog\AuthLogGridFactory::class => [DefaultGrids\AuthLogGrid::class, null],
		\ADT\FancyAdmin\UI\Components\Grids\ChangeLog\ChangeLogGridFactory::class => [DefaultGrids\ChangeLogGrid::class, null],
		\ADT\FancyAdmin\UI\Components\Grids\Configuration\ConfigurationGridFactory::class => [DefaultGrids\ConfigurationGrid::class, Entities\Configuration::class],
		\ADT\FancyAdmin\UI\Components\Grids\Identity\IdentityGridFactory::class => [DefaultGrids\IdentityGrid::class, Entities\Identity::class],
		\ADT\FancyAdmin\UI\Components\Grids\Passkey\PasskeyGridFactory::class => [DefaultGrids\PasskeyGrid::class, Entities\Passkey::class],
		\ADT\FancyAdmin\UI\Components\Grids\Profile\ProfileGridFactory::class => [DefaultGrids\ProfileGrid::class, Entities\Profile::class],
		\ADT\FancyAdmin\UI\Components\Grids\Session\SessionGridFactory::class => [DefaultGrids\SessionGrid::class, null],
		\ADT\FancyAdmin\UI\Components\Grids\Sso\SsoGridFactory::class => [DefaultGrids\SsoGrid::class, Entities\Sso::class],
		\ADT\FancyAdmin\UI\Components\Grids\Tablet\TabletGridFactory::class => [DefaultGrids\TabletGrid::class, Entities\Tablet::class],
	];

	/**
	 * ACL resource (enum implementující Resource). V neonu i jako 'App\Enum::CASE' - konstanta
	 * enumu se do konfigurace extension jinak nepředá.
	 */
	private static function resourceSchema(Resource $default): Schema
	{
		return Expect::type(Resource::class)
			->before(fn($value) => is_string($value) && str_contains($value, '::') && defined($value) ? constant($value) : $value)
			->default($default);
	}

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
			'customerAclResource' => self::resourceSchema(AclResourceNameEnum::CUSTOMER_DASHBOARD),
			'backofficeAclResource' => self::resourceSchema(AclResourceNameEnum::BACKOFFICE_DASHBOARD),
			'fullDataAclResource' => self::resourceSchema(AclResourceNameEnum::FULL_DATA),
			'personalDataAclResource' => self::resourceSchema(AclResourceNameEnum::PROFILE_PERSONAL_DATA),
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
			// Web Push (VAPID): privátní klíč EC P-256 jako PEM v base64, '' = push se neposílá
			'webPush' => Expect::structure([
				'vapidPrivateKey' => Expect::string()->default(''),
				// kontakt na provozovatele pro push služby prohlížečů (mailto: nebo https:)
				'subject' => Expect::string()->default(''),
			]),
			// Rychlá lišta napevno místo klíče quickbar v tabulce configuration (QuickbarSettings::DEFAULTS)
			'quickbar' => Expect::arrayOf('bool', 'string')->nullable()->default(null),
			// Uložené sestavy a nastavení sloupců gridů (docs/datagrid-column-filters.md)
			'datagrid' => Expect::structure([
				// Entita uložených filtrů projektu (implements GridFilter, use GridFilterTrait) - zapíná sestavy
				'presetEntity' => Expect::string()->nullable()->default(null),
				// Entita nastavení sloupců (implements GridColumnSetting, use GridColumnSettingTrait) - bez ní je nastavení v session
				'columnSettingEntity' => Expect::string()->nullable()->default(null),
			]),
			// Tablety pro vyplnění a podpis formulářů (docs/tablets.md)
			'tablet' => Expect::structure([
				'enabled' => Expect::bool()->default(false),
				'cookieName' => Expect::string()->default('fancyadmin_tablet'),
				// = rozsah service workeru, musí pokrýt čekací stránku i stránky formulářů
				'cookiePath' => Expect::string()->default('/'),
				// Entita žádosti o podpis projektu (implements TabletSignRequest), povinná s enabled
				'signRequestEntity' => Expect::string()->nullable()->default(null),
				// Entita podepsaného dokumentu projektu (implements SignedDocument) - zapíná podpis
				// dokumentu na tabletu, potřebuje adt/biometric-signature a službu TabletSignHandler
				'signedDocumentEntity' => Expect::string()->nullable()->default(null),
				// Entita podpisu projektu (implements DocumentSignature) - jeden řádek za každý
				// nakreslený podpis s biometrikou, povinná se signedDocumentEntity
				'documentSignatureEntity' => Expect::string()->nullable()->default(null),
			]),
			// Biometrický podpis (adt/biometric-signature, docs/biometric-signature.md)
			'biometricSignature' => Expect::structure([
				'enabled' => Expect::bool()->default(false),
				// Limity ověření kódem (SigningPolicy), null = výchozí hodnota knihovny
				'policy' => Expect::structure([
					'linkValidMinutes' => Expect::int()->nullable()->default(null),
					'codeValidMinutes' => Expect::int()->nullable()->default(null),
					'maxCodeAttempts' => Expect::int()->nullable()->default(null),
					'maxCodeSends' => Expect::int()->nullable()->default(null),
					'resendCooldownSeconds' => Expect::int()->nullable()->default(null),
					'codeLength' => Expect::int()->nullable()->default(null),
				]),
			]),
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
				// agent smí delegovat části dotazu na své kopie (multiagent, víc tokenů)
				'subagents' => Expect::bool()->default(false),
				// klíče AiChatOptions: idleTimeoutSeconds, maxTurnSeconds, maxToolCalls, ...
				'turn' => Expect::array()->default([]),
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
					// druh údaje => název kotvy (name: OSOBA, …); prázdné = české výchozí AI_PSEUDONYMIZATION_LABELS
					'labels' => Expect::arrayOf('string', 'string')->default([]),
					// poznámka pro AI na konci textu; null = česká výchozí AI_PSEUDONYMIZATION_NOTE
					'note' => Expect::string()->nullable()->default(null),
					// řádek pro AI u kotvy, kterou může být víc subjektů (resolver s PiiSubjectCandidates),
					// %1$s = kotva, %2$s = kotvy kandidátů; null = česká výchozí AI_PSEUDONYMIZATION_CANDIDATES_NOTE
					'candidatesNote' => Expect::string()->nullable()->default(null),
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
				// Skilly (adt/ai-chat): služba implementující ADT\AiChat\Skill\SkillProvider (třída nebo
				// Statement s argumenty); nástroje list_skills a get_skill přidá fancyadmin
				'skillProvider' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable()->default(null),
				// Služby projektu, které mají smysl jen se zapnutým AI (nástroje Tool a ToolProvider,
				// stránky AiPage, worker fronty ...) - stejný zápis jako sekce services
				'services' => Expect::array()->default([]),
				// Tajná sůl hashů v pohledech _anon (anonymization:generate-views); null = výchozí veřejná
				// sůl doctrine-anonymization. Prázdná (env chybí) příkaz zastaví.
				'anonymizationSalt' => Expect::string()->nullable()->default(null),
				// Nástroj open_page - AI otevře stránku z menu nebo stránku záznamu (služby AiPage)
				'pages' => Expect::structure([
					// menu backoffice (třída NavbarMenuFactory projektu); null = jen stránky záznamů
					'menuFactory' => Expect::string()->nullable()->default(null),
					// modul backoffice; null = z defaultBackofficeRoute (:PortalBackoffice:Home: → PortalBackoffice)
					'module' => Expect::string()->nullable()->default(null),
				]),
			]),
			// Režim zaoblení celého rozhraní, layout ho vypíše jako data-radius na <html> (_radius.scss)
			'radius' => Expect::anyOf(...FancyAdmin::RADIUS_MODES)->default('default'),
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

		$builder->addFactoryDefinition($this->prefix('widgetGridControlFactory'))
			->setImplement(WidgetGridControlFactory::class)
			->getResultDefinition()
			->setFactory(WidgetGridControl::class);

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
				'radius' => $this->config->radius,
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

		// Autowiring jen na vlastní třídu - projekt mívá vlastní službu ADT\Utils\JsComponents
		// a na typ rodiče by byli dva kandidáti
		$builder->addDefinition($this->prefix('jsComponents'))
			->setFactory(JsComponents::class)
			->setAutowired(JsComponents::class);

		$builder->addDefinition($this->prefix('quickbarSettings'))
			->setFactory(QuickbarSettings::class, ['fixedValues' => $this->config->quickbar]);

		$builder->addDefinition($this->prefix('passkeyService'))
			->setFactory(PasskeyService::class);

		$builder->addDefinition($this->prefix('secretCipher'))
			->setFactory(SecretCipher::class, ['secretKey' => $this->config->secretKey]);

		$builder->addDefinition($this->prefix('webPushSender'))
			->setFactory(WebPushSender::class, [
				'privateKey' => $this->config->webPush->vapidPrivateKey,
				'subject' => $this->config->webPush->subject,
			]);

		if ($this->config->datagrid->presetEntity !== null) {
			$builder->addDefinition($this->prefix('gridPresetStorage'))
				->setFactory(DoctrineGridPresetStorage::class, ['entityClass' => $this->config->datagrid->presetEntity]);
		}

		if ($this->config->datagrid->columnSettingEntity !== null) {
			$builder->addDefinition($this->prefix('gridColumnSettingsStorage'))
				->setFactory(DoctrineGridColumnSettingsStorage::class, ['entityClass' => $this->config->datagrid->columnSettingEntity]);
		}

		// Export z panelu gridu přes adt/exporter (docs/datagrid-export.md). Generátory musí existovat dřív, než si je
		// exporter v beforeCompile() posbírá.
		$builder->addDefinition($this->prefix('gridExports'))
			->setFactory(GridExports::class);
		$tempDir = $builder->parameters['tempDir'] ?? sys_get_temp_dir();
		$builder->addDefinition($this->prefix('gridExportXlsxGenerator'))
			->setFactory(GridExportXlsxGenerator::class, ['tempDir' => $tempDir]);
		$builder->addDefinition($this->prefix('gridExportCsvGenerator'))
			->setFactory(GridExportCsvGenerator::class, ['tempDir' => $tempDir]);
		$builder->addDefinition($this->prefix('gridExportOwner'))
			->setFactory(GridExportOwner::class);

		// Potřebuje projektovou entitu Tablet a TabletQueryFactory, proto jen na požádání
		if ($this->config->tablet->enabled) {
			if ($this->config->tablet->signRequestEntity === null) {
				throw new RuntimeException('fancyadmin: tablet.enabled je zapnuté, ale chybí tablet.signRequestEntity - entita žádosti o podpis projektu (implements TabletSignRequest, use TabletSignRequestTrait). Viz docs/tablets.md.');
			}

			$builder->addDefinition($this->prefix('tabletSignRequests'))
				->setFactory(DoctrineTabletSignRequests::class, ['entityClass' => $this->config->tablet->signRequestEntity]);

			if ($this->config->tablet->signedDocumentEntity !== null && !class_exists(BiometricSigning::class)) {
				throw new RuntimeException('fancyadmin: tablet.signedDocumentEntity zapíná podpis dokumentu na tabletu, ale chybí balíček adt/biometric-signature (composer require adt/biometric-signature). Viz docs/tablets.md.');
			}

			if ($this->config->tablet->signedDocumentEntity !== null && $this->config->tablet->documentSignatureEntity === null) {
				throw new RuntimeException('fancyadmin: tablet.signedDocumentEntity zapíná podpis dokumentu na tabletu, ale chybí tablet.documentSignatureEntity - entita podpisu projektu (implements DocumentSignature, use DocumentSignatureTrait). Viz docs/tablets.md.');
			}

			// Podpis dokumentu na tabletu - TabletSignHandler projektu si služba autowiruje;
			// bez něj nebo bez entit je podpis vypnutý (TabletSigning::isEnabled())
			$builder->addDefinition($this->prefix('tabletSigning'))
				->setFactory(TabletSigning::class, [
					'signedDocumentClass' => $this->config->tablet->signedDocumentEntity,
					'documentSignatureClass' => $this->config->tablet->documentSignatureEntity,
				]);

			// auditní stopa podpisu jako HTML (administrace projektu, podklad pro PDF)
			$builder->addDefinition($this->prefix('tabletSignAuditRenderer'))
				->setFactory(TabletSignAuditRenderer::class);

			$builder->addDefinition($this->prefix('tabletService'))
				->setFactory(TabletService::class, [
					'cookieName' => $this->config->tablet->cookieName,
					'cookiePath' => $this->config->tablet->cookiePath,
				]);
		}

		// Biometrický podpis - služby knihovny. SigningHandler (SMS, obsah důkazního
		// záznamu, PDF) dodává projekt jako vlastní službu, BiometricSigning si ho autowiruje.
		if ($this->config->biometricSignature->enabled) {
			if (!class_exists(BiometricSigning::class)) {
				throw new RuntimeException('fancyadmin: biometricSignature.enabled je zapnuté, ale chybí balíček adt/biometric-signature (composer require adt/biometric-signature). Viz docs/biometric-signature.md.');
			}

			$builder->addDefinition($this->prefix('biometricSignature.policy'))
				->setFactory(SigningPolicy::class, array_filter((array) $this->config->biometricSignature->policy, fn($value) => $value !== null));

			$builder->addDefinition($this->prefix('biometricSignature.signing'))
				->setFactory(BiometricSigning::class);

			$builder->addDefinition($this->prefix('biometricSignature.evidenceVerifier'))
				->setFactory(EvidenceVerifier::class);
		}

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

		// Vestavěné nástroje (SQL nad _anon, tabulka, graf, CSV); MCP server je dostává jménem
		$builder->addDefinition($this->prefix('ai.toolHandler'))
			->setFactory(ToolHandler::class, ['exportEnabled' => $ai->exportEnabled])
			->setAutowired(false);

		$builder->addDefinition($this->prefix('ai.openPageTool'))
			->setFactory(OpenPageTool::class, [
				'pages' => new Statement('typed', [AiPage::class]),
				'module' => $ai->pages->module ?? explode(':', trim($this->config->defaultBackofficeRoute, ':'))[0],
				'menuFactory' => $ai->pages->menuFactory,
			]);

		// Nástroje chatu = vestavěné + všechny služby projektu (i fancyadminu) typu ADT\AiChat\Tool\Tool
		// a nástroje služeb ToolProvider; ChatService i AgentProvisioner ho dostanou autowiringem (ToolExecutor)
		$builder->addDefinition($this->prefix('ai.toolExecutor'))
			->setFactory(CompositeToolExecutor::class, [
				'base' => $this->prefix('@ai.toolHandler'),
				'tools' => new Statement('typed', [Tool::class]),
				'providers' => new Statement('typed', [ToolProvider::class]),
			]);

		if ($ai->skillProvider !== null) {
			$builder->addDefinition($this->prefix('ai.skillProvider'))
				->setFactory($ai->skillProvider);
			$builder->addDefinition($this->prefix('ai.listSkillsTool'))
				->setFactory(ListSkillsTool::class, [
					'description' => ListSkillsTool::DEFAULT_DESCRIPTION
						. ' Not needed when one of your tools does the action by itself (e.g. open_page) - call that tool directly.',
				]);
			$builder->addDefinition($this->prefix('ai.getSkillTool'))
				->setFactory(GetSkillTool::class);
		}

		// Služby projektu jen pro AI (ai.services) - bez zapnutého AI by jim chyběly služby fancyadminu
		$this->loadDefinitionsFromConfig($ai->services);

		$builder->addDefinition($this->prefix('ai.piiValueCollector'))
			->setFactory(PiiValueCollector::class);

		$builder->addDefinition($this->prefix('ai.piiResponseFilter'))
			->setFactory(PiiResponseFilter::class, ['labels' => $ai->piiLabels]);

		$builder->addDefinition($this->prefix('ai.turnOptions'))
			->setFactory(AiChatOptions::class, $ai->turn);

		$builder->addDefinition($this->prefix('ai.systemPromptBuilder'))
			->setFactory(SystemPromptBuilder::class, [
				'templatePath' => $ai->systemPromptPath ?? SystemPromptBuilder::defaultTemplatePath(),
			]);

		$builder->addDefinition($this->prefix('ai.agentProvisioner'))
			->setFactory(AgentProvisioner::class, [
				'agentClass' => $ai->agentClass,
				'namePrefix' => $ai->agentNamePrefix ?? $this->config->project ?? 'fancyadmin',
				'subagents' => $ai->subagents,
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

			// pravidla vlastníka tokenu - projekt může službu přepsat (např. bezstavové přihlášení v runAs())
			$builder->addDefinition($this->prefix('ai.mcpTokenPolicy'))
				->setFactory(AiMcpTokenPolicy::class);

			// ověření osobních tokenů AI - použitelné i pro vlastní API projektu pro jiné AI
			$builder->addDefinition($this->prefix('ai.mcpTokenAuthenticator'))
				->setFactory(McpTokenAuthenticator::class, [
					'tokenClass' => $ai->mcp->tokenClass,
					'policy' => $this->prefix('@ai.mcpTokenPolicy'),
				]);

			$builder->addDefinition($this->prefix('ai.mcpServerEndpoint'))
				->setFactory(McpEndpoint::class, [
					'server' => $this->prefix('@ai.mcpServer'),
					'authenticator' => $this->prefix('@ai.mcpTokenAuthenticator'),
				])
				->setAutowired(false);

			$builder->addDefinition($this->prefix('ai.mcpEndpoint'))
				->setFactory(AiMcpEndpoint::class, ['endpoint' => $this->prefix('@ai.mcpServerEndpoint')]);
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
				// České výchozí hodnoty fancyadminu, ai-chat má anglické
				'labels' => $pseudonymization->labels ?: self::AI_PSEUDONYMIZATION_LABELS,
				'note' => $pseudonymization->note ?? self::AI_PSEUDONYMIZATION_NOTE,
				'candidatesNote' => $pseudonymization->candidatesNote ?? self::AI_PSEUDONYMIZATION_CANDIDATES_NOTE,
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
			$command = $builder->addDefinition($this->prefix('ai.anonymization.generateViewsCommand'))
				->setFactory(GenerateAnonymizedViewsCommand::class)
				->setAutowired(false);
			if ($ai->anonymizationSalt !== null) {
				$command->addSetup('setSalt', [$ai->anonymizationSalt]);
			}
		}
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$this->registerDefaults();
		$this->checkSignalCsrfRouter();

		$builder->getDefinition($this->prefix('gridExports'))
			->setArguments([array_map(static fn(string $name): string => '@' . $name, array_keys($builder->findByType(GridExport::class)))]);
		foreach ($builder->findByType(Exporter::class) as $exporter) {
			$exporter->addSetup('$onExport[]', [[$builder->getDefinition($this->prefix('gridExportOwner')), 'stamp']]);
		}

		// Push do stránek (docs/push.md): bez hubu prázdná implementace, stránky pollují
		if ($builder->getByType(Push::class) === null) {
			$builder->addDefinition($this->prefix('push'))
				->setFactory(NullPush::class);
		}

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
	 * Výchozí query (DEFAULT_QUERIES) tam, kde projekt vlastní továrnu nemá - projekt pak píše
	 * jen query, které opravdu mění. Vlastní továrna projektu (search v neonu) má vždy přednost.
	 */
	private function registerDefaults(): void
	{
		$builder = $this->getContainerBuilder();
		$appDir = $builder->parameters['appDir'] ?? null;
		$plan = self::planDefaultQueries(
			static fn(string $factory): bool => $builder->findByType($factory) !== [],
			static fn(string $interface): array => self::findProjectEntityClasses(is_string($appDir) ? $appDir : null, $interface),
		);
		self::addDefaultQueryDefinitions($builder, $this->prefix('defaultQuery'), $plan);

		$findEntities = static fn(string $interface): array => self::findProjectEntityClasses(is_string($appDir) ? $appDir : null, $interface);
		self::addDefaultComponentDefinitions($builder, $this->prefix('defaultComponent'), self::planDefaultComponents(
			static fn(string $factory): bool => $builder->findByType($factory) !== [],
			$findEntities,
		));
	}

	/**
	 * Které výchozí query zaregistrovat: jen bez továrny projektu a s právě jednou entitou projektu.
	 *
	 * @param callable(class-string): bool $hasFactory má projekt vlastní továrnu
	 * @param callable(class-string): class-string[] $findEntities entity projektu s daným rozhraním
	 * @return array<class-string, array{class-string, class-string|null}> továrna => [query, entita]
	 */
	public static function planDefaultQueries(callable $hasFactory, callable $findEntities): array
	{
		return self::planDefaults(self::DEFAULT_QUERIES, $hasFactory, $findEntities, true);
	}

	/**
	 * Formuláře a gridy: bez továrny projektu a s aspoň jednou entitou projektu (třídu entity
	 * si formulář zjistí sám přes EntityManager::findEntityClassByInterface()).
	 *
	 * @param callable(class-string): bool $hasFactory
	 * @param callable(class-string): class-string[] $findEntities
	 * @return array<class-string, array{class-string, class-string|null}>
	 */
	public static function planDefaultComponents(callable $hasFactory, callable $findEntities): array
	{
		return self::planDefaults(self::DEFAULT_COMPONENTS, $hasFactory, $findEntities, false);
	}

	/**
	 * @param array<class-string, array{class-string, class-string|null}> $table
	 * @return array<class-string, array{class-string, class-string|null}>
	 */
	private static function planDefaults(array $table, callable $hasFactory, callable $findEntities, bool $requireSingle): array
	{
		$plan = [];
		foreach ($table as $factory => [$class, $entityInterface]) {
			if ($hasFactory($factory)) {
				continue;
			}

			$entityClass = null;
			if ($entityInterface !== null) {
				$entityClasses = $findEntities($entityInterface);
				if ($requireSingle && count($entityClasses) > 1) {
					throw new RuntimeException('fancyadmin: výchozí ' . $class . ' neví, kterou entitu použít - ' . $entityInterface . ' implementují ' . implode(', ', $entityClasses) . '. Vytvořte vlastní třídu a ' . $factory . ' podle README.');
				}
				if ($entityClasses === []) {
					continue;
				}
				$entityClass = $entityClasses[0];
			}

			$plan[$factory] = [$class, $entityClass];
		}

		return $plan;
	}

	/**
	 * @param array<class-string, array{class-string, class-string|null}> $plan viz planDefaultComponents()
	 */
	public static function addDefaultComponentDefinitions(ContainerBuilder $builder, string $prefix, array $plan): void
	{
		foreach ($plan as $factory => [$class]) {
			$builder->addFactoryDefinition($prefix . '.' . lcfirst(substr(strrchr($class, '\\'), 1)))
				->setImplement($factory)
				->getResultDefinition()
				->setFactory($class)
				->addTag(InjectExtension::TagInject, true);
		}
	}

	/**
	 * @param array<class-string, array{class-string, class-string|null}> $plan viz planDefaultQueries()
	 */
	public static function addDefaultQueryDefinitions(ContainerBuilder $builder, string $prefix, array $plan): void
	{
		foreach ($plan as $factory => [$query, $entityClass]) {
			$result = $builder->addFactoryDefinition($prefix . '.' . lcfirst(substr(strrchr($query, '\\'), 1)))
				->setImplement($factory)
				->getResultDefinition()
				->setFactory($query, $entityClass !== null ? ['entityClass' => $entityClass] : []);

			if (is_a($query, DefaultBaseQuery::class, true)) {
				$result->addSetup('setSecurityUser');
			}
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

		// $form->addBiometricSignature() - jen s nainstalovaným adt/biometric-signature,
		// bez něj by pole nemělo z čeho skládat hodnotu (SignatureCapture)
		if (class_exists(\ADT\BiometricSignature\SignatureCapture::class)) {
			$this->getInitialization()->addBody(BiometricSignatureInput::class . '::register();');
		}
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}