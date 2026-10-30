<?php

declare(strict_types=1);

use ADT\AiChat\Pseudonymization\OpenAiCompatiblePiiDetector;
use ADT\AiChat\Pseudonymization\PiiCheckUnavailableException;
use ADT\AiChat\Pseudonymization\PseudonymInterface;
use ADT\AiChat\Pseudonymization\PseudonymizedText;
use ADT\AiChat\Pseudonymization\Pseudonymizer;
use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\Model\AiChat\AiChatTranslator;
use ADT\FancyAdmin\Model\AiChat\AuditPiiCheckListener;
use ADT\FancyAdmin\Model\Audit\AuditActor;
use ADT\FancyAdmin\Model\Audit\AuditLogger;
use ADT\FancyAdmin\Model\Entities\AiMessageTrait;
use ADT\FancyAdmin\Model\Entities\AiPseudonymTrait;
use ADT\FancyAdmin\Tests\Fixtures\TestAiConversation;
use ADT\FancyAdmin\Tests\Fixtures\TestAiPseudonym;
use ADT\FancyAdmin\Tests\Fixtures\TestAuditLog;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestSecurityUser;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Nette\DI\Compiler;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Schema\Processor;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;

/**
 * Pseudonymizace textu uživatele před AI (adt/ai-chat Pseudonymizer): konfigurace
 * ai.pseudonymization, registrace služeb, entita kotev kompatibilní s tabulkou ai_pseudonym,
 * audit ai_pii_check bez hodnot a překlady.
 */

require __DIR__ . '/bootstrap.php';


const COLORS = [
	'backgroundColor' => '#fff', 'dashboardAccentColor' => '#fff', 'primaryColor' => '#fff',
	'primaryColorDark' => '#fff', 'primaryColorDark20' => '#fff', 'secondaryColor' => '#fff',
	'secondaryColorDark' => '#fff', 'secondaryColorDarker' => '#fff', 'ternaryColor' => '#fff',
	'ternaryTextColor' => '#fff', 'loginBackground' => '#fff', 'loginInputTextColor' => '#fff',
	'loginBackgroundInput' => '#fff', 'loginBackgroundInputFocus' => '#fff', 'inputBorder' => '#fff',
	'inputFocusBorder' => '#fff', 'inputFocusBackground' => '#fff',
];


/** Rozšíření s konfigurací a kompilátorem (bez sestavení kontejneru) */
function createExtension(array $ai): FancyAdminExtension
{
	$extension = new FancyAdminExtension();
	$extension->setCompiler(new Compiler(), 'fancyadmin');
	$extension->setConfig(new Processor()->process(
		$extension->getConfigSchema(),
		['locksDir' => '/var/lock', 'colors' => COLORS, 'ai' => $ai],
	));

	return $extension;
}

function callPrivate(FancyAdminExtension $extension, string $method, mixed ...$args): mixed
{
	return new ReflectionMethod($extension, $method)->invoke($extension, ...$args);
}

/** @return array{0: AuditPiiCheckListener, 1: \ADT\FancyAdmin\Tests\Fixtures\TestConnection} */
function createListener(): array
{
	$em = new TestEntityManager([TestAuditLog::class]);
	$metadata = $em->getClassMetadata(TestAuditLog::class);
	$metadata->setPrimaryTable(['name' => 'audit_log']);
	foreach (['action', 'outcome', 'createdAt', 'correlationId', 'createdById', 'createdByLabel', 'createdBy', 'sourceIp', 'userAgent', 'payload'] as $field) {
		$metadata->mapField([
			'fieldName' => $field,
			'type' => 'string',
			'columnName' => strtolower((string) preg_replace('~([a-z])([A-Z])~', '$1_$2', $field)),
		]);
	}
	// Tah běží ve frontě - bez přihlášeného uživatele a bez HTTP požadavku
	$actor = new AuditActor(new TestSecurityUser(isLoggedIn: false), new Request(new UrlScript('http://localhost/')));

	return [new AuditPiiCheckListener(new AuditLogger($em), $actor), $em->getConnection()];
}

function conversation(int $id): TestAiConversation
{
	$conversation = new TestAiConversation();
	new ReflectionProperty(TestAiConversation::class, 'id')->setValue($conversation, $id);

	return $conversation;
}


test('pseudonymizace je ve vychozi konfiguraci vypnuta a kontrola povinna', function () {
	$config = createExtension([])->getConfig();

	Assert::false($config->ai->pseudonymization->enabled);
	Assert::true($config->ai->pseudonymization->required);
	Assert::null($config->ai->pseudonymization->pseudonymClass);
	Assert::null($config->ai->pseudonymization->detector->url);
	Assert::same(20, $config->ai->pseudonymization->detector->timeout);
});


test('zapnuta pseudonymizace bez entity kotev je chyba konfigurace', function () {
	Assert::exception(
		fn() => callPrivate(createExtension(['pseudonymization' => ['enabled' => true]]), 'loadAiPseudonymization'),
		RuntimeException::class,
		'%a%ai.pseudonymization.pseudonymClass%a%',
	);
});


test('zapnuta pseudonymizace registruje detektor, pseudonymizer a audit', function () {
	$extension = createExtension(['pseudonymization' => [
		'enabled' => true,
		'pseudonymClass' => TestAiPseudonym::class,
		'resolver' => '@patientResolver',
		'subjectAnchor' => '[PACIENT #%s]',
		'labels' => ['name' => 'OSOBA'],
		'detector' => ['url' => 'http://qwen.test', 'model' => 'qwen3'],
	]]);
	callPrivate($extension, 'loadAiPseudonymization');
	$builder = $extension->getContainerBuilder();

	$detector = $builder->getDefinition('fancyadmin.ai.piiDetector');
	Assert::same(OpenAiCompatiblePiiDetector::class, $detector->getFactory()->getEntity());
	Assert::same('http://qwen.test', $detector->getFactory()->arguments['url']);
	Assert::true($detector->getFactory()->arguments['required']);

	$arguments = $builder->getDefinition('fancyadmin.ai.pseudonymizer')->getFactory()->arguments;
	Assert::same(Pseudonymizer::class, $builder->getDefinition('fancyadmin.ai.pseudonymizer')->getFactory()->getEntity());
	Assert::same(TestAiPseudonym::class, $arguments['pseudonymClass']);
	Assert::same('@patientResolver', $arguments['resolver']);
	Assert::same('[PACIENT #%s]', $arguments['subjectAnchor']);
	Assert::false(isset($arguments['note']), 'null = výchozí poznámka ai-chat');

	Assert::same(AuditPiiCheckListener::class, $builder->getDefinition('fancyadmin.ai.piiCheckListener')->getFactory()->getEntity());
});


test('resolver bez pseudonymizace null, jako trida se vytvori', function () {
	$extension = createExtension(['pseudonymization' => ['enabled' => true, 'pseudonymClass' => TestAiPseudonym::class, 'resolver' => 'App\PatientResolver']]);
	callPrivate($extension, 'loadAiPseudonymization');

	$resolver = $extension->getContainerBuilder()->getDefinition('fancyadmin.ai.pseudonymizer')->getFactory()->arguments['resolver'];
	Assert::same('App\PatientResolver', $resolver->getEntity());

	$extension = createExtension(['pseudonymization' => ['enabled' => true, 'pseudonymClass' => TestAiPseudonym::class]]);
	callPrivate($extension, 'loadAiPseudonymization');
	Assert::null($extension->getContainerBuilder()->getDefinition('fancyadmin.ai.pseudonymizer')->getFactory()->arguments['resolver']);
});


test('pri pseudonymizaci jsou prilohy ve vychozim stavu vypnute, vyslovne nastaveni projektu zustane', function () {
	$extension = createExtension([]);

	Assert::same([], callPrivate($extension, 'getAiChatOptions', [], false));
	Assert::same(['attachments' => ['maxCount' => 0]], callPrivate($extension, 'getAiChatOptions', [], true));
	Assert::same(
		['rateLimitPerMinute' => 5, 'attachments' => ['maxCount' => 0, 'maxBytes' => 100]],
		callPrivate($extension, 'getAiChatOptions', ['rateLimitPerMinute' => 5, 'attachments' => ['maxBytes' => 100]], true),
	);
	Assert::same(['attachments' => ['maxCount' => 2]], callPrivate($extension, 'getAiChatOptions', ['attachments' => ['maxCount' => 2]], true));
});


test('entita kotev je z anonymizovaneho schematu vyrazena', function () {
	$excluded = callPrivate(createExtension(['pseudonymization' => ['pseudonymClass' => TestAiPseudonym::class]]), 'getAiExcludedEntities');

	Assert::contains(TestAiPseudonym::class, $excluded);
	Assert::notContains(TestAiPseudonym::class, callPrivate(createExtension([]), 'getAiExcludedEntities'));
});


test('entita kotev odpovida tabulce ai_pseudonym a hodnota se anonymizuje', function () {
	Assert::type(PseudonymInterface::class, new TestAiPseudonym());

	$conversation = new ReflectionProperty(AiPseudonymTrait::class, 'conversation');
	Assert::same('AiConversation', $conversation->getAttributes(ManyToOne::class)[0]->newInstance()->targetEntity);
	$join = $conversation->getAttributes(JoinColumn::class)[0]->newInstance();
	Assert::false($join->nullable);
	Assert::same('CASCADE', $join->onDelete);

	$anchor = new ReflectionProperty(TestAiPseudonym::class, 'anchor')->getAttributes(Column::class)[0]->newInstance();
	Assert::same(40, $anchor->length);
	$value = new ReflectionProperty(TestAiPseudonym::class, 'value');
	Assert::same('text', $value->getAttributes(Column::class)[0]->newInstance()->type);
	Assert::same(AnonymizationType::NOTE, $value->getAttributes(Anonymize::class)[0]->newInstance()->type);
});


test('zprava dedi sent_content a maskuje ho', function () {
	$sentContent = new ReflectionProperty(AiMessageTrait::class, 'sentContent');

	Assert::same('sent_content', $sentContent->getAttributes(Column::class)[0]->newInstance()->name);
	Assert::same(AnonymizationType::NOTE, $sentContent->getAttributes(Anonymize::class)[0]->newInstance()->type);
});


test('audit kontroly zapise pocty, nikdy hodnoty', function () {
	[$listener, $connection] = createListener();

	$listener->checked(conversation(12), new PseudonymizedText('Pošli [PACIENT #3] … poznámka', 'Pošli [PACIENT #3]', ['PACIENT' => 1], true));

	$insert = $connection->getLastInsert()['data'];
	Assert::same('ai_pii_check', $insert['action']);
	Assert::same(AuditLogger::OUTCOME_SUCCESS, $insert['outcome']);
	Assert::equal(['conversation_id' => 12, 'qwen' => true, 'findings' => ['PACIENT' => 1]], $insert['payload']);
	Assert::null($insert['source_ip']);
});


test('neprovedena povinna kontrola jde do auditu jako selhani', function () {
	[$listener, $connection] = createListener();

	$listener->unavailable(conversation(12), PiiCheckUnavailableException::create('detector down'));

	$insert = $connection->getLastInsert()['data'];
	Assert::same(AuditLogger::OUTCOME_FAILURE, $insert['outcome']);
	Assert::equal(['conversation_id' => 12, 'reason' => 'pii_check_unavailable'], $insert['payload']);
});


test('texty pseudonymizace jsou ve slovniku cs i sk', function () {
	foreach (['cs', 'sk'] as $_locale) {
		$aiChat = Yaml::parseFile(__DIR__ . '/../src/lang/fcadmin.' . $_locale . '.yml')['aiChat'];

		Assert::true(isset($aiChat['error']['piiCheckUnavailable']), $_locale);
		Assert::true(isset($aiChat['labels']['sentContent']), $_locale);
	}
	Assert::contains('sentContent', AiChatTranslator::WIDGET_LABELS);

	// Přesný text hlídá i akceptační test adt-dentu
	$cs = Yaml::parseFile(__DIR__ . '/../src/lang/fcadmin.cs.yml')['aiChat'];
	Assert::same('Zpráva nebyla odeslána AI - kontrola osobních údajů je teď nedostupná. Zkuste to prosím za chvíli.', $cs['error']['piiCheckUnavailable']);
	Assert::same('Osobní údaje jsme před AI skryli - zobrazit, co AI dostala', $cs['labels']['sentContent']);
});
