<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;

/**
 * Prekladove katalogy balicku.
 *
 * Cestina je referencni: kazdy klic, na ktery se balicek odkazuje, v ni musi byt,
 * a slovenstina se od ni nesmi lisit vic, nez je tady vyjmenovane.
 */

require __DIR__ . '/bootstrap.php';


const LANG_DIR = __DIR__ . '/../src/lang';

function flatten(array $catalogue, string $prefix = ''): array
{
	$flat = [];
	foreach ($catalogue as $key => $value) {
		$path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
		if (is_array($value)) {
			$flat += flatten($value, $path);
		} else {
			$flat[$path] = $value;
		}
	}

	return $flat;
}

function catalogue(string $locale): array
{
	return flatten(Yaml::parseFile(LANG_DIR . '/fcadmin.' . $locale . '.yml'));
}

/** @return array<string, list<string>> klic => soubory, ve kterych se pouziva */
function usedTranslationKeys(): array
{
	$used = [];
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src'));

	foreach ($files as $file) {
		if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'latte'], true)) {
			continue;
		}

		preg_match_all('~[\'"](fcadmin\.[A-Za-z0-9_.]+)[\'"]~', (string) file_get_contents($file->getPathname()), $matches);
		foreach ($matches[1] as $key) {
			$used[$key][] = $file->getFilename();
		}
	}

	return $used;
}


test('katalogy jsou platny YAML a nejsou prazdne', function () {
	Assert::true(count(catalogue('cs')) > 100);
	Assert::true(count(catalogue('sk')) > 100);
});


test('vsechny preklady jsou retezce', function () {
	foreach (['cs', 'sk'] as $locale) {
		foreach (catalogue($locale) as $key => $value) {
			Assert::type('string', $value, $locale . ': ' . $key);
		}
	}
});


test('prazdny je jen zamerne prazdny popisek sloupce', function () {
	// grids.session.isCurrent je hlavicka sloupce s ikonou - nadpis mit nema.
	foreach (['cs', 'sk'] as $locale) {
		$empty = array_keys(array_filter(catalogue($locale), fn(string $value) => $value === ''));
		sort($empty);

		Assert::same(['grids.session.isCurrent'], $empty, $locale);
	}
});


test('oddelovac tisicu je vsude nezlomitelna mezera', function () {
	// Obycejna mezera by cenu pustila na dva radky - proto U+00A0 ve vsech jazycich.
	foreach (['cs', 'sk'] as $locale) {
		Assert::same(',', catalogue($locale)['appGeneral.model.filters.decimalSeparator'], $locale);
		Assert::same("\u{00A0}", catalogue($locale)['appGeneral.model.filters.thousandsSeparator'], $locale);
	}
});


test('oba katalogy maji stejne klice', function () {
	// Cestina je referencni - chybejici slovensky klic by se projevil az vypsanym klicem
	// misto textu, a to jen uzivateli se slovenskym jazykem.
	Assert::same([], array_keys(array_diff_key(catalogue('cs'), catalogue('sk'))));
	Assert::same([], array_keys(array_diff_key(catalogue('sk'), catalogue('cs'))));
});


test('vsechny klice pouzite v balicku v cestine existuji', function () {
	$catalogue = catalogue('cs');
	$missing = [];

	foreach (usedTranslationKeys() as $key => $files) {
		// Klic konciciny teckou je prefix skladany za behu - resi ho samostatny test.
		if (str_ends_with($key, '.')) {
			continue;
		}

		if (!array_key_exists(substr($key, strlen('fcadmin.')), $catalogue)) {
			$missing[$key] = $files[0];
		}
	}

	Assert::same([], $missing);
});


test('prefixy skladane za behu maji v katalogu svou vetev', function () {
	$catalogue = Yaml::parseFile(LANG_DIR . '/fcadmin.cs.yml');

	foreach (usedTranslationKeys() as $key => $files) {
		if (!str_ends_with($key, '.')) {
			continue;
		}

		$node = $catalogue;
		foreach (explode('.', trim(substr($key, strlen('fcadmin.')), '.')) as $part) {
			Assert::true(is_array($node) && array_key_exists($part, $node), $key . ' (' . $files[0] . ')');
			$node = $node[$part];
		}

		Assert::type('array', $node, $key);
	}
});


test('rozsireni hlasi adresar s preklady', function () {
	$resources = new FancyAdminExtension()->getTranslationResources();

	Assert::count(1, $resources);
	Assert::same(realpath(LANG_DIR), realpath($resources[0]));
});


test('v adresari jsou jen ocekavane katalogy', function () {
	$files = array_map('basename', glob(LANG_DIR . '/*') ?: []);
	sort($files);

	Assert::same(['fcadmin.cs.yml', 'fcadmin.sk.yml'], $files);
});
