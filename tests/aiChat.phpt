<?php

declare(strict_types=1);

use ADT\AiChat\Entity\AgentInterface;
use ADT\AiChat\Entity\ConversationInterface;
use ADT\AiChat\Entity\MessageInterface;
use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\FancyAdmin\Model\AiChat\AiChatTranslator;
use ADT\FancyAdmin\Model\Entities\AiConversationTrait;
use ADT\FancyAdmin\Model\Entities\ApiKeyTrait;
use ADT\FancyAdmin\Model\Entities\IdentityTrait;
use ADT\FancyAdmin\Model\Entities\PasskeyTrait;
use ADT\FancyAdmin\Tests\Fixtures\TestAiAgent;
use ADT\FancyAdmin\Tests\Fixtures\TestAiConversation;
use ADT\FancyAdmin\Tests\Fixtures\TestAiMessage;
use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestTranslator;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;

/**
 * AI asistent (adt/ai-chat): entitni kontrakty, anonymizace osobnich udaju,
 * preklady widgetu a konfigurace rozsireni.
 */

require __DIR__ . '/bootstrap.php';


function anonymizeType(string $trait, string $property): ?AnonymizationType
{
	$attributes = new ReflectionProperty($trait, $property)->getAttributes(Anonymize::class);

	return $attributes ? $attributes[0]->newInstance()->type : null;
}


test('AI entity splnuji rozhrani adt/ai-chat', function () {
	Assert::type(ConversationInterface::class, new TestAiConversation());
	Assert::type(MessageInterface::class, new TestAiMessage());
	Assert::type(AgentInterface::class, new TestAiAgent());
});


test('konverzace patri identite', function () {
	$conversation = new TestAiConversation();
	$identity = new TestIdentity();

	Assert::same($conversation, $conversation->setIdentity($identity));
	Assert::same($identity, $conversation->getIdentity());
	Assert::true(new ReflectionProperty(AiConversationTrait::class, 'identity')->getAttributes(Doctrine\ORM\Mapping\ManyToOne::class) !== []);
});


test('osobni udaje identity se anonymizuji', function () {
	Assert::same(AnonymizationType::FIRST_NAME, anonymizeType(IdentityTrait::class, 'firstName'));
	Assert::same(AnonymizationType::LAST_NAME, anonymizeType(IdentityTrait::class, 'lastName'));
	Assert::same(AnonymizationType::EMAIL, anonymizeType(IdentityTrait::class, 'email'));
	Assert::same(AnonymizationType::PHONE, anonymizeType(IdentityTrait::class, 'phoneNumber'));
	Assert::same(AnonymizationType::SECRET, anonymizeType(IdentityTrait::class, 'password'));
});


test('tajne udaje se anonymizuji', function () {
	Assert::same(AnonymizationType::SECRET, anonymizeType(ApiKeyTrait::class, 'key'));
	Assert::same(AnonymizationType::SECRET, anonymizeType(PasskeyTrait::class, 'credentialId'));
	Assert::same(AnonymizationType::SECRET, anonymizeType(PasskeyTrait::class, 'publicKey'));
});


test('preklad chyb ai-chat jde pres slovnik fancyadminu, chybejici klic spadne na vychozi text', function () {
	$translator = new AiChatTranslator(new TestTranslator([
		'fcadmin.aiChat.error.emptyMessage' => 'Zpráva nesmí být prázdná.',
	]));

	Assert::same('Zpráva nesmí být prázdná.', $translator->translate('aiChat.error.emptyMessage', [], 'The message cannot be empty.'));
	Assert::same('Fallback', $translator->translate('aiChat.error.unknownKey', [], 'Fallback'));
});


test('widget dostane jen prelozene texty', function () {
	$translator = new AiChatTranslator(new TestTranslator([
		'fcadmin.aiChat.labels.send' => 'Odeslat',
	]));

	Assert::same(['send' => 'Odeslat'], $translator->getWidgetLabels());
});


test('urovne premysleni jdou widgetu vnorene v effortLevels', function () {
	$translator = new AiChatTranslator(new TestTranslator([
		'fcadmin.aiChat.labels.effort' => 'Přemýšlení',
		'fcadmin.aiChat.labels.effortLevels.high' => 'Vysoké',
	]));

	Assert::same(['effort' => 'Přemýšlení', 'effortLevels' => ['high' => 'Vysoké']], $translator->getWidgetLabels());
});


test('vsechny texty widgetu jsou ve slovniku a zastupne symboly zustavaji pro widget', function () {
	foreach (['cs', 'sk'] as $_locale) {
		$labels = Yaml::parseFile(__DIR__ . '/../src/lang/fcadmin.' . $_locale . '.yml')['aiChat']['labels'];

		Assert::same(AiChatTranslator::EFFORT_LEVELS, array_keys($labels['effortLevels']), $_locale);
		unset($labels['effortLevels']);
		Assert::same(AiChatTranslator::WIDGET_LABELS, array_keys($labels), $_locale);
		Assert::contains('{max}', $labels['errorTooManyFiles'], $_locale);
		Assert::contains('{name}', $labels['errorFileTooLarge'], $_locale);
	}
});


test('bocni panel umi misto formulare vlastni sablonu', function () {
	$control = new SidePanelControl();

	Assert::same($control, $control->setBodyTemplate(__DIR__ . '/../src/UI/Presenters/Ai/sidePanel.latte', ['aiChatConfig' => []]));
});
