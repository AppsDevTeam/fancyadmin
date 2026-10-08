<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use ADT\FancyAdmin\Tests\Fixtures\TestTemplate;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInButton;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormTrait;
use ADT\Forms\Controls\PasswordRevealInput;
use ADT\Forms\Form;
use Nette\Application\UI\Control;
use Tester\Assert;

/**
 * Prihlasovaci formular - rozlozeni sekci a dalsi tlacitka pod formularem.
 *
 * Projekty si driv kvuli tlacitku externiho prihlaseni (T-Mobile SSO) delaly vlastni sablonu
 * prihlasovaci stranky. Hacek getSignInButtons() to resi bez ni, tlacitka se vykresli
 * v sekci formulare se stejnymi mezerami jako prihlaseni klicem.
 */

require __DIR__ . '/bootstrap.php';

// V aplikaci to dela FancyAdminExtension pri inicializaci kontejneru
PasswordRevealInput::register();


class DefaultSignInForm extends Control
{
	use SignInFormTrait;

	public function __construct(bool $passkeyEnabled = false)
	{
		$this->_fancyAdmin = FancyAdminFactory::create(['passkeyEnabled' => $passkeyEnabled]);
		// getTemplate() je finalni, sablona se bez presenteru da podstrcit jen do property
		new ReflectionProperty(Control::class, 'template')->setValue($this, new TestTemplate());
	}
}


class SignInFormWithButtons extends DefaultSignInForm
{
	/** @var list<SignInButton> */
	public array $buttons = [];

	protected function getSignInButtons(): array
	{
		return $this->buttons;
	}
}


/** @return array<string, ADT\Forms\Section> */
function sectionsByName(Form $form): array
{
	$sections = [];
	foreach ($form->getSections() as $_section) {
		$sections[$_section->getName()] = $_section;
	}

	return $sections;
}


function initForm(DefaultSignInForm $control): Form
{
	$form = new Form();
	$control->initForm($form);

	return $form;
}


test('tlacitko drzi popisek, cil odkazu, parametry i ikonku', function () {
	$button = new SignInButton('app.sso.loginButton', ':Portal:Sso:in', ['backlink' => 'abc'], 'fa-solid fa-mobile');

	Assert::same('app.sso.loginButton', $button->label);
	Assert::same(':Portal:Sso:in', $button->destination);
	Assert::same(['backlink' => 'abc'], $button->args);
	Assert::same('fa-solid fa-mobile', $button->icon);
});


test('tlacitko je bez parametru a bez ikonky, pokud je projekt neuvede', function () {
	$button = new SignInButton('app.sso.loginButton', ':Portal:Sso:in');

	Assert::same([], $button->args);
	Assert::null($button->icon);
});


test('balicek sam zadna dalsi tlacitka nepridava', function () {
	$control = new DefaultSignInForm();
	$form = initForm($control);

	Assert::same([], new ReflectionMethod($control, 'getSignInButtons')->invoke($control));
	Assert::false(isset(sectionsByName($form)['signInButtons']));
	Assert::same([], $control->getTemplate()->signInButtons);
});


test('tlacitka z projektu dostanou vlastni sekci a jdou do sablony', function () {
	$control = new SignInFormWithButtons();
	$control->buttons = [
		new SignInButton('app.sso.first', ':Portal:First:in'),
		new SignInButton('app.sso.second', ':Portal:Second:in'),
	];
	$form = initForm($control);

	Assert::true(isset(sectionsByName($form)['signInButtons']));
	Assert::same($control->buttons, $control->getTemplate()->signInButtons);
});


test('tlacitka jsou az za odeslanim formulare i za prihlasenim klicem', function () {
	// Sekce bez prvku se vykresli za prvkem, ktery byl ve formulari pridany pred ni
	$control = new SignInFormWithButtons(passkeyEnabled: true);
	$control->buttons = [new SignInButton('app.sso.loginButton', ':Portal:Sso:in')];
	$form = initForm($control);

	$names = array_keys(sectionsByName($form));

	Assert::true(array_search('passkey', $names, true) < array_search('signInButtons', $names, true));
	Assert::same('submit', array_key_last(iterator_to_array($form->getComponents())));
});


test('odkaz na zapomenute heslo je v sekci s inputy hned pod heslem', function () {
	// Mimo inputsWrap ho od hesla oddeloval gap formulare a projekty ho vracely zapornym marginem
	$form = initForm(new DefaultSignInForm());
	$sections = sectionsByName($form);

	Assert::true(isset($sections['lostPassword']));
	Assert::contains($sections['inputsWrap'], $sections['lostPassword']->getAncestorSections());
});
