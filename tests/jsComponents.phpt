<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Services\JsComponents;
use Nette\Utils\Json;
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


test('vlastni komponenty se prilinaji k existujicim', function () {
	$components = new JsComponents();
	$components->setComponents(['datepicker' => ['locale' => 'cs']]);

	$components->setComponents(['translate' => ['all' => []]]);

	Assert::same(['locale' => 'cs'], config($components)['datepicker']);
	Assert::same(['all' => []], config($components)['translate']);
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
