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


class StackedPanelEntity implements ADT\DoctrineComponents\Entities\Entity
{
	public function __construct(private readonly int $id)
	{
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function isNew(): bool
	{
		return false;
	}
}


final class StackedPanelStringableEntity extends StackedPanelEntity implements Stringable
{
	public function __construct(int $id, private readonly string $name)
	{
		parent::__construct($id);
	}

	public function __toString(): string
	{
		return $this->name;
	}
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


test('bocni panel se ve vychozim stavu nevrstvi a kresli do hlavniho snippetu', function () {
	$control = new SidePanelControl();

	Assert::false($control->isStacked());
	Assert::same('sidePanel', $control->getSnippetName());
});


test('vrstveny bocni panel kresli do vlastniho snippetu', function () {
	$control = new SidePanelControl();

	Assert::same($control, $control->setStacked());
	Assert::true($control->isStacked());
	Assert::same('sidePanelStacked', $control->getSnippetName());

	$control->setStacked(false);
	Assert::false($control->isStacked());
	Assert::same('sidePanel', $control->getSnippetName());
});


test('vysledek vrstveneho panelu bere popisek z __toString entity', function () {
	$form = new ADT\DoctrineForms\Form();
	$form->setEntity(new StackedPanelStringableEntity(42, 'Jan Novak'));

	Assert::same(['value' => 42, 'label' => 'Jan Novak'], new SidePanelControl()->getResult($form));
});


test('vysledek vrstveneho panelu bez __toString pouzije jako popisek id', function () {
	$form = new ADT\DoctrineForms\Form();
	$form->setEntity(new StackedPanelEntity(7));

	Assert::same(['value' => 7, 'label' => '7'], new SidePanelControl()->getResult($form));
});


test('vlastni popisek vysledku ma prednost pred __toString', function () {
	$form = new ADT\DoctrineForms\Form();
	$form->setEntity(new StackedPanelStringableEntity(42, 'Jan Novak'));

	$control = new SidePanelControl();
	Assert::same($control, $control->setResultLabel(fn(StackedPanelStringableEntity $entity) => 'Pacient ' . $entity->getId()));

	Assert::same(['value' => 42, 'label' => 'Pacient 42'], $control->getResult($form));
});


test('formular bez entity nema vysledek vrstveneho panelu', function () {
	Assert::null(new SidePanelControl()->getResult(new ADT\DoctrineForms\Form()));
});


test('tlacitko pro vrstveny panel je ajaxovy odkaz s ikonou plus', function () {
	$button = SidePanelControl::createStackedButton('/patients?do=newPatient');

	Assert::same('a', $button->getName());
	Assert::same('/patients?do=newPatient', $button->getAttribute('href'));
	Assert::same('btn ajax', $button->getAttribute('class'));
	Assert::true($button->getAttribute('data-fancyadmin-side-panel-stacked'));
	Assert::same('<a href="/patients?do=newPatient" class="btn ajax" data-fancyadmin-side-panel-stacked><i class="fa-solid fa-plus"></i></a>', (string) $button);
});


test('tlacitko pro vrstveny panel umi popisek a vlastni ikonu', function () {
	$button = SidePanelControl::createStackedButton('/new', 'Novy pacient', 'fa-solid fa-user-plus');

	Assert::same('Novy pacient', $button->getAttribute('title'));
	Assert::same('Novy pacient', $button->getAttribute('aria-label'));
	Assert::contains('<i class="fa-solid fa-user-plus"></i>', (string) $button);
});


test('layout obsahuje snippet i zaviraci tlacitko vrstveneho panelu', function () {
	$layout = file_get_contents(__DIR__ . '/../src/UI/Presenters/@layout.latte');

	Assert::match('~<div class="side-panel-template-container side-panel-template-container--stacked">\s*\{snippet sidePanelStacked\}\{/snippet\}\s*<button type="button" class="btn-close"></button>~', $layout);
	Assert::contains('<div class="side-panel-template-backdrop side-panel-template-backdrop--stacked"></div>', $layout);
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
