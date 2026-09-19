<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestControl;
use ADT\FancyAdmin\Tests\Fixtures\TestControlWithoutTemplate;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\Forms\IsActiveFormField;
use ADT\FancyAdmin\UI\RenderToStringTrait;
use ADT\Forms\Form;
use Tester\Assert;

/**
 * Pomocne traity komponent - hledani sablony, vykresleni do retezce a bocni panel.
 */

require __DIR__ . '/bootstrap.php';


class FormWithIsActiveField
{
	use IsActiveFormField;
}


test('sablona se hleda vedle tridy komponenty', function () {
	$control = new TestControl();

	Assert::same(realpath(__DIR__ . '/Fixtures/TestControl.latte'), realpath($control->callGetTemplateFile()));
});


test('bez sablony se vrati null', function () {
	Assert::null(new TestControlWithoutTemplate()->callGetTemplateFile());
});


test('sablona se hleda i u rozhrani, jehoz nazev obsahuje nazev tridy', function () {
	// Balicek dodava sablonu k rozhrani, konkretni tridu si vytvori projekt.
	$control = new ADT\FancyAdmin\Tests\Fixtures\PanelControl();

	Assert::same(
		realpath(__DIR__ . '/Fixtures/Panel/PanelControl.latte'),
		realpath($control->callGetTemplateFile()),
	);
});


test('vykresleni do retezce zachyti vystup', function () {
	Assert::same('obsah komponenty', new TestControl()->renderToString());
});


test('vykresleni do retezce nenecha nic ve vystupnim bufferu', function () {
	$level = ob_get_level();

	new TestControl()->renderToString();

	Assert::same($level, ob_get_level());
});


test('bocni panel ma vychozi velikost a potvrzeni zavreni', function () {
	$control = new SidePanelControl();

	Assert::same(SidePanelSize::Medium, new ReflectionProperty($control, 'size')->getValue($control));
	Assert::same('fcadmin.sidePanels.control.closeConfirm', new ReflectionProperty($control, 'closeConfirm')->getValue($control));
});


test('velikost i potvrzeni bocniho panelu jdou zmenit', function () {
	$control = new SidePanelControl();

	Assert::same($control, $control->setSize(SidePanelSize::Large));
	Assert::same(SidePanelSize::Large, new ReflectionProperty($control, 'size')->getValue($control));

	Assert::same($control, $control->setCloseConfirm('muj.preklad'));
	Assert::same('muj.preklad', new ReflectionProperty($control, 'closeConfirm')->getValue($control));
});


test('tovarna na bocni panel vraci komponentu', function () {
	Assert::same(SidePanelControl::class, new ReflectionMethod(SidePanelControlFactory::class, 'create')->getReturnType()->getName());
});


test('bocni panel umi vykreslit sam sebe do retezce', function () {
	Assert::true(in_array(RenderToStringTrait::class, class_uses(SidePanelControl::class), true));
});


test('sablona bocniho panelu je soucasti balicku', function () {
	Assert::true(is_file(__DIR__ . '/../src/UI/Components/Controls/SidePanel/SidePanelControl.latte'));
});


test('pole aktivity se prida jako zaskrtavatko s vychozim ano', function () {
	$form = new Form();

	new FormWithIsActiveField()->addIsActiveField($form);

	Assert::type(Nette\Forms\Controls\Checkbox::class, $form['isActive']);
	Assert::same('fcadmin.forms.user.labels.isActive', $form['isActive']->getCaption());
	Assert::true($form['isActive']->getValue());
});


test('popisek pole aktivity jde prebit', function () {
	$form = new Form();

	new FormWithIsActiveField()->addIsActiveField($form, 'Aktivni zaznam');

	Assert::same('Aktivni zaznam', $form['isActive']->getCaption());
});
