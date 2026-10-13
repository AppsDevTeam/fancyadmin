<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Services\JsComponents;
use Nette\Utils\Json;
use Nette\Utils\Random;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
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
	$a = Random::generate(12);
	$b = Random::generate(12);

	$components->setFirebaseKnownTokens([3 => $a, 7 => $b]);

	Assert::same(Json::encode(['notifications' => ['knownTokens' => [$a, $b]]]), $components->generateConfig());
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


test('preklady pro JavaScript bere z libovolneho translatoru s katalogy', function () {
	// Driv byl parametr typovany na App\Model\Translator - tridu projektu, ktera v balicku
	// neexistuje, takze metodu nesel zavolat bez projektu. Staci rozhrani se `getCatalogue()`.
	$catalogue = new MessageCatalogue('cs', ['appJs' => ['save' => 'Ulozit'], 'app' => ['other' => 'Jine']]);
	$translator = new class ($catalogue) implements TranslatorBagInterface {
		public function __construct(private readonly MessageCatalogue $catalogue)
		{
		}

		public function getCatalogue(?string $locale = null): MessageCatalogueInterface
		{
			return $this->catalogue;
		}

		public function getCatalogues(): array
		{
			return [$this->catalogue];
		}
	};

	$components = new JsComponents();
	$components->setTranslateConfig($translator);

	Assert::same(['save' => 'Ulozit'], config($components)['translate']['all']);
});
