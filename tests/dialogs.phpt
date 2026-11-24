<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tester\Assert;

/**
 * Potvrzovaci dialogy (assets/js/dialog.js) misto nativnich confirm(), alert() a prompt().
 *
 * Nativni dialog nejde nastylovat, nese texty prohlizece a zablokuje celou stranku.
 * Inline handler typu onclick="confirm(...)" navic CSP ze security.neon vubec nespusti.
 */

require __DIR__ . '/bootstrap.php';


const ROOT_DIR = __DIR__ . '/..';

/** @return list<string> soubory s danymi priponami pod adresarem balicku */
function files(string $dir, array $extensions): array
{
	$files = [];
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_DIR . '/' . $dir)) as $file) {
		if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
			$files[] = $file->getPathname();
		}
	}

	sort($files);

	return $files;
}

function withoutComments(string $source): string
{
	$source = preg_replace('~/\*.*?\*/~s', '', $source);
	$source = preg_replace('~(^|[^:\'"])//[^\n]*~', '$1', $source);

	return preg_replace('~\{\*.*?\*\}~s', '', $source);
}

/** @return array<string, string> */
function catalogue(string $locale): array
{
	$flat = [];
	$walk = function (array $items, string $prefix) use (&$walk, &$flat): void {
		foreach ($items as $key => $value) {
			$path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
			is_array($value) ? $walk($value, $path) : $flat[$path] = $value;
		}
	};
	$walk(Yaml::parseFile(ROOT_DIR . '/src/lang/fcadmin.' . $locale . '.yml'), '');

	return $flat;
}

function source(string $path): string
{
	return (string) file_get_contents(ROOT_DIR . '/' . $path);
}


test('JS ani sablony balicku nevolaji nativni dialogy', function () {
	$files = [...files('assets/js', ['js']), ...files('src', ['latte'])];
	Assert::true(count($files) > 0);

	foreach ($files as $_file) {
		$calls = preg_match_all('~(?<![\w.$])(?:window\.)?(confirm|alert|prompt)\s*\(~', withoutComments((string) file_get_contents($_file)), $matches);

		Assert::same(0, $calls, $_file . ' vola nativni ' . implode(', ', $matches[1] ?? []) . '() - pouzij showConfirm/showAlert z assets/js/dialog.js');
	}
});


test('sablony nemaji inline handler s potvrzenim', function () {
	foreach (files('src', ['latte']) as $_file) {
		Assert::notContains('onclick=', (string) file_get_contents($_file), $_file);
	}
});


test('kazdy text dialogu je prelozeny cesky i slovensky', function () {
	$keys = [
		'dialog.confirm',
		'dialog.cancel',
		'dialog.close',
		'sidePanels.control.closeConfirm',
		'sidePanels.control.closeConfirmText',
		'sidePanels.control.closeConfirmDiscard',
		'sidePanels.control.closeConfirmContinue',
	];

	foreach (['cs', 'sk'] as $_locale) {
		$catalogue = catalogue($_locale);

		foreach ($keys as $_key) {
			Assert::true(isset($catalogue[$_key]) && trim($catalogue[$_key]) !== '', "fcadmin.$_locale.yml: chybi $_key");
		}
	}
});


test('layout predava vychozi texty tlacitek dialogu', function () {
	$layout = source('src/UI/Presenters/@layout.latte');

	Assert::contains('data-fcadmin-dialog', $layout);
	Assert::contains('data-confirm="{_fcadmin.dialog.confirm}"', $layout);
	Assert::contains('data-cancel="{_fcadmin.dialog.cancel}"', $layout);
	Assert::contains('data-close="{_fcadmin.dialog.close}"', $layout);
});


test('konfigurace dialogu je mimo snippet body', function () {
	// Prekreslenim snippetu `body` by element zmizel a dialog by spadl na anglicke texty.
	$layout = source('src/UI/Presenters/@layout.latte');

	Assert::true(strpos($layout, 'data-fcadmin-dialog') < strpos($layout, 'n:snippet="body"'));
});


test('side panel posila do JS vsechny texty dialogu neulozenych zmen', function () {
	$template = source('src/UI/Components/Controls/SidePanel/SidePanelControl.latte');

	Assert::contains('data-close-confirm="{_$closeConfirm}"', $template);
	Assert::contains('data-close-confirm-text="{_fcadmin.sidePanels.control.closeConfirmText}"', $template);
	Assert::contains('data-close-confirm-discard="{_fcadmin.sidePanels.control.closeConfirmDiscard}"', $template);
	Assert::contains('data-close-confirm-continue="{_fcadmin.sidePanels.control.closeConfirmContinue}"', $template);
});


test('side panel se pri neulozenych zmenach pta pres modal', function () {
	$script = source('assets/js/sidePanel.js');

	Assert::contains("import { showConfirm } from './dialog';", $script);
	Assert::match('~showConfirm\(\{.*?primary: \'cancel\'.*?onConfirm: \(\) => closeSidePanel\(panelId\)~s', $script);
});


test('odhlaseni ze vsech zarizeni se potvrzuje pres link-confirmation', function () {
	$template = source('src/UI/Presenters/Profile/default.latte');

	Assert::contains('class="dropdown-item text-danger link-confirmation" data-confirm-text="{_fcadmin.presenters.profile.logoutAllConfirm}"', $template);
});


test('datagrid potvrzuje pres dialog i ajaxove pozadavky', function () {
	$script = source('assets/js/datagrid/datagrid.js');

	Assert::contains("import { confirmClick, isConfirmed } from '../dialog';", $script);
	Assert::same(2, substr_count($script, 'return datagridConfirm(settings);'), 'naja i nette.ajax vetev');
});


test('dialog je soucasti aplikace i stylu', function () {
	Assert::contains("import './dialog';", source('assets/js/app.js'));
	Assert::contains("@use 'dialog';", source('assets/scss/app.scss'));
});


test('dialog potvrzuje odkazy v capture fazi pred handlery na elementu', function () {
	$script = source('assets/js/dialog.js');

	Assert::contains("closest?.('a.link-confirmation, [data-datagrid-confirm]')", $script);
	Assert::match('~document\.addEventListener\(\'click\', .*?\}, true\);~s', $script);
	Assert::contains('export const showConfirm', $script);
	Assert::contains('export const showAlert', $script);
});
