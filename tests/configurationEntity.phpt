<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\Tests\Fixtures\TestConfiguration;
use Nette\Utils\JsonException;
use Tester\Assert;

/**
 * ConfigurationTrait - konfiguracni polozka administrace.
 */

require __DIR__ . '/bootstrap.php';


test('polozka ma klic, nazev a hodnotu', function () {
	$config = new TestConfiguration('password.policy.admin', 'Politika hesel');

	Assert::same('password.policy.admin', $config->getKey());
	Assert::same('Politika hesel', $config->getName());
	Assert::null($config->getValue());

	$config->setKey('jiny.klic')->setName('Jiny nazev')->setValue('hodnota');

	Assert::same('jiny.klic', $config->getKey());
	Assert::same('Jiny nazev', $config->getName());
	Assert::same('hodnota', $config->getValue());
	Assert::null($config->setValue(null)->getValue());
});


test('vychozi typ je prosty text', function () {
	$config = new TestConfiguration();

	Assert::same(ConfigurationTypeEnum::TYPE_PLAINTEXT, $config->getType());
	Assert::same(ConfigurationTypeEnum::TYPE_JSON, $config->setType(ConfigurationTypeEnum::TYPE_JSON)->getType());
});


test('soubor je nepovinny', function () {
	$config = new TestConfiguration();

	Assert::null($config->getFile());
	Assert::null($config->setFile(null)->getFile());
});


test('volby se ukladaji jako JSON', function () {
	$config = new TestConfiguration();

	$config->setOptions(['a' => 'Ano', 'b' => 'Ne']);

	Assert::same('{"a":"Ano","b":"Ne"}', $config->getOptions());
	Assert::same(['a' => 'Ano', 'b' => 'Ne'], $config->getOptions(asArray: true));
});


test('volby jdou ulozit i jako hotovy retezec', function () {
	$config = new TestConfiguration();

	$config->setOptions('{"a":"Ano"}');

	Assert::same('{"a":"Ano"}', $config->getOptions());
	Assert::same(['a' => 'Ano'], $config->getOptions(asArray: true));
});


test('prazdne volby vraci null nebo prazdne pole', function () {
	$config = new TestConfiguration();

	Assert::null($config->getOptions());
	Assert::same([], $config->getOptions(asArray: true));

	// Prazdne pole i prazdny retezec se ulozi jako NULL.
	Assert::null($config->setOptions([])->getOptions());
	Assert::null($config->setOptions('')->getOptions());
	Assert::null($config->setOptions(null)->getOptions());
	Assert::same([], $config->getOptions(asArray: true));
});


test('POZOR: retezec "0" se ulozi jako zadne volby', function () {
	// Podminka v setOptions() je "truthy", takze retezec "0" propadne na NULL.
	Assert::null(new TestConfiguration()->setOptions('0')->getOptions());
});


test('rozbity JSON se pozna az pri cteni do pole', function () {
	$config = new TestConfiguration()->setOptions('{neni json');

	Assert::same('{neni json', $config->getOptions());
	Assert::exception(fn() => $config->getOptions(asArray: true), JsonException::class);
});
