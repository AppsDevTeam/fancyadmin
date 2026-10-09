<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Filters;
use ADT\FancyAdmin\Tests\Fixtures\TestTemplate;
use ADT\FancyAdmin\Tests\Fixtures\TestTranslator;
use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Tester\Assert;

/**
 * Latte filtry administrace.
 *
 * Vychozi oddelovace jsou nezlomitelne mezery (U+00A0) - v kodu vypadaji jako obycejne,
 * takze je testy uvadi explicitne, aby se pripadna zmena poznala.
 */

require __DIR__ . '/bootstrap.php';


const NBSP = "\u{00A0}";

function createFilters(array $messages = []): Filters
{
	return new Filters('/var/www/shared', new TestTranslator($messages + [
		'fcadmin.appGeneral.model.filters.decimalSeparator' => ',',
		'fcadmin.appGeneral.model.filters.thousandsSeparator' => NBSP,
		'fcadmin.appGeneral.model.filters.empty' => 'nevyplneno',
	]));
}


test('sdileny adresar se propise do sablony', function () {
	$template = new TestTemplate();

	createFilters()->setSharedDir($template);

	Assert::same('/var/www/shared', $template->sharedDir);
});


test('cisla se formatuji s nezlomitelnou mezerou', function () {
	$filters = createFilters();

	Assert::same('1' . NBSP . '000', $filters->number(1000));
	Assert::same('1' . NBSP . '234' . NBSP . '567', $filters->number(1234567));
	Assert::same('0', $filters->number(0));
});


test('koncove nuly v desetinne casti se orezavaji', function () {
	$filters = createFilters();

	Assert::same('1' . NBSP . '234,57', $filters->number(1234.5678, 2));
	Assert::same('1' . NBSP . '234,5', $filters->number(1234.5, 2));
	// Cele cislo se vrati bez desetinneho oddelovace.
	Assert::same('10', $filters->number(10.0, 2));
	Assert::same('10', $filters->number(10.004, 2));
});


test('u retezce se pocet desetinnych mist odvodi ze vstupu', function () {
	$filters = createFilters();

	// Vstup ma jen jedno desetinne misto, takze se druhe nedoplnuje.
	Assert::same('1' . NBSP . '234,5', $filters->number('1234.5', 2));
	Assert::same('1' . NBSP . '234,5', $filters->number('1234.50', 2));
	// Vic desetinnych mist nez limit se zaokrouhli na limit.
	Assert::same('1' . NBSP . '234,57', $filters->number('1234.567', 2));
	Assert::same('1' . NBSP . '234', $filters->number('1234', 2));
});


test('zaporna cisla a zaokrouhleni', function () {
	$filters = createFilters();

	Assert::same('-1' . NBSP . '234,5', $filters->number(-1234.5, 2));
	Assert::same('1' . NBSP . '235', $filters->number(1234.5678, 0));
	Assert::same('1', $filters->number(0.999, 2));
});


test('oddelovace jdou prebit', function () {
	$filters = createFilters();

	Assert::same('1,234.57', $filters->number(1234.5678, 2, '.', ','));
	Assert::same('1234,57', $filters->number(1234.5678, 2, ',', ''));
});


test('nectitelny vstup skonci nulou', function () {
	Assert::same('0', createFilters()->number('abc', 2));
});


test('datum a cas', function () {
	$filters = createFilters();

	Assert::same('5.' . NBSP . '1.' . NBSP . '2026', $filters->date('2026-01-05'));
	Assert::same('5.' . NBSP . '1.' . NBSP . '2026 07:09', $filters->datetime('2026-01-05 07:09'));
	Assert::same('07:09', $filters->time('2026-01-05 07:09'));
	Assert::same('2026-01-05', $filters->date('2026-01-05', 'Y-m-d'));
	Assert::same('05.01.2026 07:09:33', $filters->datetime('2026-01-05 07:09:33', 'd.m.Y H:i:s'));
});


test('format se dekoduje z HTML entit', function () {
	// Sablony format casto zapisuji jako 'j.&nbsp;n.&nbsp;Y', aby datum nespadlo na dva radky.
	$filters = createFilters();

	Assert::same('5.' . NBSP . '1.' . NBSP . '2026', $filters->date('2026-01-05', 'j.&nbsp;n.&nbsp;Y'));
	Assert::same('5.' . NBSP . '1.' . NBSP . '2026 07:09', $filters->datetime('2026-01-05 07:09', 'j.&nbsp;n.&nbsp;Y H:i'));
});


test('prazdne datum se nepreklada', function () {
	$filters = createFilters();

	Assert::null($filters->date(null));
	Assert::null($filters->datetime(null));
});


test('datum prijme i timestamp a DateTime', function () {
	$filters = createFilters();

	Assert::same('2026-01-05', $filters->date(new DateTimeImmutable('2026-01-05 10:00'), 'Y-m-d'));
	Assert::same('2026-01-05', $filters->date(strtotime('2026-01-05 10:00'), 'Y-m-d'));
});


test('cena se sklada z cisla a meny oddelene nezlomitelnou mezerou', function () {
	// Mena se od cisla oddeluje U+00A0, aby cena nespadla na dva radky.
	$filters = createFilters();

	Assert::same('1' . NBSP . '234,5' . NBSP . 'Kc', $filters->price(1234.5, 'Kc'));
	Assert::same('1' . NBSP . '234,57' . NBSP . 'Kc', $filters->price('1234.567', 'Kc'));
});


test('bez meny se oddelovac vubec neprida', function () {
	// Drive tu zustavala nezlomitelna mezera - trim() ji po bajtech odriznout neumi.
	$filters = createFilters();

	Assert::same('1' . NBSP . '234,5', $filters->price(1234.5, ''));
	Assert::same('1' . NBSP . '234,5', $filters->price(1234.5, '   '));
	Assert::same('1' . NBSP . '234,5', $filters->priceNullable(1234.5, ''));
});


test('mena s prebytecnymi mezerami se orizne', function () {
	Assert::same('10' . NBSP . 'Kc', createFilters()->price(10, '  Kc  '));
});


test('oriznuti meny nerozbije vicebajtove znaky', function () {
	// trim() pracuje po bajtech; kdyby se do charlistu pridala U+00A0 (0xC2 0xA0),
	// orizly by se i koncove bajty jinych znaku - napriklad 'a' s prizvukem konci 0xA0.
	$filters = createFilters();

	Assert::same('10' . NBSP . 'à', $filters->price(10, 'à'));
	Assert::same('10' . NBSP . 'Kč', $filters->price(10, 'Kč'));
	Assert::same('10' . NBSP . '€', $filters->price(10, '€'));
});


test('mena se dekoduje z HTML entit', function () {
	Assert::same('10' . NBSP . '€', createFilters()->price(10, '&euro;'));
});


test('oddelovace ceny bere z prekladu', function () {
	$filters = new Filters('/shared', new TestTranslator([
		'fcadmin.appGeneral.model.filters.decimalSeparator' => '.',
		'fcadmin.appGeneral.model.filters.thousandsSeparator' => ',',
	]));

	Assert::same('1,234.5' . NBSP . 'EUR', $filters->price(1234.5, 'EUR'));
	// Explicitni oddelovace maji prednost pred prekladem.
	Assert::same('1 234|5' . NBSP . 'EUR', $filters->price(1234.5, 'EUR', 2, '|', ' '));
});


test('cena bez hodnoty se vypise pomlckami', function () {
	$filters = createFilters();

	Assert::same('---', $filters->priceNullable(null, 'Kc'));
	Assert::same('1' . NBSP . '234,5' . NBSP . 'Kc', $filters->priceNullable(1234.5, 'Kc'));
	// Nula je hodnota, ne prazdno.
	Assert::same('0' . NBSP . 'Kc', $filters->priceNullable(0.0, 'Kc'));
});


test('prazdna hodnota se nahradi popiskem', function () {
	$filters = createFilters();
	$info = new FilterInfo();

	Assert::same('<span class="empty">nevyplneno</span>', $filters->ifEmpty($info, ''));
	Assert::same(ContentType::Html, $info->contentType);

	// Nula ani "0" prazdne nejsou - (string) '0' neni ''.
	Assert::same('0', $filters->ifEmpty(new FilterInfo(), '0'));
	Assert::same('text', $filters->ifEmpty(new FilterInfo(), 'text'));
});


test('neprazdna hodnota nemeni content type', function () {
	$info = new FilterInfo();

	createFilters()->ifEmpty($info, 'text');

	Assert::null($info->contentType);
});


test('null se vykresli jako prazdno', function () {
	$info = new FilterInfo();

	Assert::same('<span class="empty">nevyplneno</span>', createFilters()->ifEmpty($info, null));
});


test('logicka hodnota se vykresli ikonou', function () {
	$filters = createFilters();

	Assert::contains('fa-square-check', $filters->boolIcon(new FilterInfo(), true));
	Assert::contains('fa-square-xmark', $filters->boolIcon(new FilterInfo(), false));
	Assert::contains('fa-regular fa-square', $filters->boolIcon(new FilterInfo(), null));

	$info = new FilterInfo();
	$filters->boolIcon($info, true);
	Assert::same(ContentType::Html, $info->contentType);
});


test('ztmaveni a zesvetleni barvy', function () {
	$filters = createFilters();

	Assert::same('#2952a3', $filters->darken('#3366cc', 10));
	Assert::same('#3870e0', $filters->lighten('#3366cc', 10));
	// Nula procent barvu nemeni.
	Assert::same('#3366cc', $filters->lighten('#3366cc', 0));
});


test('zkraceny zapis barvy se rozbali', function () {
	$filters = createFilters();

	Assert::same($filters->darken('#336699', 10), $filters->darken('#369', 10));
	Assert::same($filters->lighten('#336699', 10), $filters->lighten('#369', 10));
	Assert::same(Filters::invertColor('#336699'), Filters::invertColor('#369'));
});


test('mrizka na zacatku je nepovinna', function () {
	$filters = createFilters();

	Assert::same($filters->darken('#3366cc', 10), $filters->darken('3366cc', 10));
	Assert::same($filters->lighten('#3366cc', 10), $filters->lighten('3366cc', 10));
	Assert::same(Filters::invertColor('#3366cc'), Filters::invertColor('3366cc'));
});


test('zesvetleni se zastavi na bile', function () {
	$filters = createFilters();

	Assert::same('#ffffff', $filters->lighten('#ffffff', 50));
	// Cerna se zesvetlit neda - nasobi se nulou.
	Assert::same('#000000', $filters->lighten('#000000', 50));
});


test('inverze barvy', function () {
	Assert::same('#ffffff', Filters::invertColor('#000000'));
	Assert::same('#000000', Filters::invertColor('#ffffff'));
	Assert::same('#00ffff', Filters::invertColor('#ff0000'));
	// Dvakrat invertovana barva je puvodni.
	Assert::same('#3366cc', Filters::invertColor(Filters::invertColor('#3366cc')));
});


test('ztmaveni zvlada i sede odstiny', function () {
	// U barvy, kde R = G = B, je odstin nedefinovany a ($max - $min) nula - drive se tu
	// delilo nulou, a to i u bile a cerne, tedy u zcela beznych hodnot v konfiguraci.
	$filters = createFilters();

	Assert::same('#e6e6e6', $filters->darken('#ffffff', 10));
	Assert::same('#676767', $filters->darken('#808080', 10));
	Assert::same('#1a1a1a', $filters->darken('#333333', 10));
	Assert::same('#1a1a1a', $filters->darken('#333', 10));
});


test('sede odstiny se ztmavuji o dane procento svetlosti', function () {
	$filters = createFilters();

	// 10 % svetlosti je 25,5 z 255 - z 0x80 (128) tak zbyde 103.
	Assert::same('#808080', $filters->darken('#808080', 0));
	Assert::same('#676767', $filters->darken('#808080', 10));
	Assert::same('#4d4d4d', $filters->darken('#808080', 20));
	// Ztmaveni se zastavi na cerne, nejde do zaporu.
	Assert::same('#000000', $filters->darken('#808080', 100));
	Assert::same('#000000', $filters->darken('#000000', 10));
});


test('oprava sedych odstinu nezmenila barevne hodnoty', function () {
	$filters = createFilters();

	Assert::same('#2952a3', $filters->darken('#3366cc', 10));
	Assert::same('#cc0000', $filters->darken('#ff0000', 10));
});
