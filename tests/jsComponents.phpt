<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Services\JsComponents;
use Nette\Utils\Json;
use Tester\Assert;

/**
 * JsComponents - konfigurace, kterou balicek predava do JavaScriptu.
 */

require __DIR__ . '/bootstrap.php';


function config(JsComponents $components): array
{
	return Json::decode($components->generateConfig(), forceArrays: true);
}


test('cerstva konfigurace je prazdna', function () {
	Assert::same([], config(new JsComponents()));
});


test('Firebase konfigurace se zapise pro notifikace i messaging', function () {
	$components = new JsComponents();
	$firebaseConfig = ['apiKey' => 'abc', 'projectId' => 'test'];

	$components->setFirebaseConfig($firebaseConfig);

	Assert::same([
		'notifications' => ['initializeConfig' => $firebaseConfig],
		'messaging' => ['initializeConfig' => $firebaseConfig],
	], config($components));
});


test('odkazy notifikaci se pridavaji k Firebase konfiguraci', function () {
	$components = new JsComponents();
	$components->setFirebaseConfig(['apiKey' => 'abc']);

	Assert::same($components, $components->setFirebaseLink('subscribe', '/api/subscribe'));
	$components->setFirebaseLink('unsubscribe', '/api/unsubscribe');

	$notifications = config($components)['notifications'];
	Assert::same(['apiKey' => 'abc'], $notifications['initializeConfig']);
	Assert::same('/api/subscribe', $notifications['subscribe']);
	Assert::same('/api/unsubscribe', $notifications['unsubscribe']);
});


test('odkaz jde nastavit i bez Firebase konfigurace', function () {
	$components = new JsComponents()->setFirebaseLink('subscribe', '/api/subscribe');

	Assert::same(['notifications' => ['subscribe' => '/api/subscribe']], config($components));
});


test('zname tokeny se ukladaji jako seznam', function () {
	// Klice pole se zahazuji - v JSON musi vzniknout pole, ne objekt.
	$components = new JsComponents();

	$components->setFirebaseKnownTokens([3 => 'token-a', 7 => 'token-b']);

	Assert::same('{"notifications":{"knownTokens":["token-a","token-b"]}}', $components->generateConfig());
});


test('prazdny seznam tokenu zustane polem', function () {
	$components = new JsComponents()->setFirebaseKnownTokens([]);

	Assert::same('{"notifications":{"knownTokens":[]}}', $components->generateConfig());
});


test('vlastni komponenty se prilinaji k existujicim', function () {
	$components = new JsComponents();
	$components->setFirebaseConfig(['apiKey' => 'abc']);

	$components->setComponents(['datepicker' => ['locale' => 'cs']]);

	Assert::same(['apiKey' => 'abc'], config($components)['notifications']['initializeConfig']);
	Assert::same(['locale' => 'cs'], config($components)['datepicker']);
});


test('POZOR: setTranslateConfig je navazany na tridu projektu', function () {
	// Parametr je typovany na App\Model\Translator, coz je trida projektu, ne balicku.
	// V balicku ta trida neexistuje, takze metodu nejde zavolat bez projektu.
	$parameter = new ReflectionMethod(JsComponents::class, 'setTranslateConfig')->getParameters()[0];

	Assert::same('App\Model\Translator', (string) $parameter->getType());
	Assert::false(class_exists('App\Model\Translator'));
});
