<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\ColumnFiltersTrait;
use ADT\FancyAdmin\UI\Components\Grids\FilterChip;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Bridges\ApplicationLatte\UIExtension;
use Nette\Bridges\FormsLatte\FormsExtension;
use Nette\Utils\Html;
use Tester\Assert;

/**
 * Filtr v hlavičce sloupce (ColumnFiltersTrait, DataGridColumnFilters.latte, docs/datagrid-column-filters.md):
 * šablona, kde je pole filtru, typy polí, čipy a jejich hodnoty, výchozí filtr.
 */

require __DIR__ . '/bootstrap.php';

final class ColumnFiltersGrid extends DataGrid
{
	use ColumnFiltersTrait;
}

function columnFiltersGrid(): ColumnFiltersGrid
{
	$grid = new ColumnFiltersGrid();
	$grid->addColumnText('name', 'Jméno')->setFilterText();
	$grid->addColumnText('orderDate', 'Datum');
	$grid->addColumnText('state', 'Stav');
	$grid->addColumnText('isActive', 'Aktivní');
	$grid->addFilterDateRange('orderDate', 'Datum');
	$grid->addFilterSelect('state', 'Stav', [
		'' => 'Vše',
		'Čeká' => [
			'gate' => Html::el('option')->setHtml('&nbsp; &nbsp; Brána'),
			'account' => Html::el('option')->setHtml('&nbsp; &nbsp; Účet'),
		],
		'done' => 'Hotovo',
	]);
	$grid->addFilterSelect('active', 'Aktivní', ['' => 'Vše', 1 => 'Ano', 0 => 'Ne'], 'isActive');
	$grid->addFilterMultiSelect('tags', 'Štítky', ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D']);

	return $grid;
}

/** @param list<FilterChip> $chips */
function chipValues(array $chips): array
{
	$values = [];
	foreach ($chips as $chip) {
		$values[$chip->key] = $chip->value;
	}

	return $values;
}

test('šablona gridu je DataGridColumnFilters.latte, dědí ze šablony knihovny', function () {
	$grid = new ColumnFiltersGrid();

	Assert::same(ColumnFiltersGrid::COLUMN_FILTERS_TEMPLATE, $grid->getOriginalTemplateFile());
	Assert::same((new DataGrid())->getOriginalTemplateFile(), $grid->getColumnFiltersParentTemplate());
});

test('šablona se přeloží', function () {
	$latte = new Engine();
	$latte->setTempDirectory(null);
	$latte->addExtension(new TranslatorExtension(null));
	$latte->addExtension(new UIExtension(null));
	$latte->addExtension(new FormsExtension());

	Assert::noError(fn() => $latte->compile(ColumnFiltersGrid::COLUMN_FILTERS_TEMPLATE));
});

test('panel filtru patří sloupci se stejným klíčem, jinak sloupci, nad kterým filtr hledá', function () {
	$grid = columnFiltersGrid();
	$filters = $grid->getPanelFilters([], []);

	Assert::same('name', $grid->getFilterColumn($filters['name']));
	Assert::same('isActive', $grid->getFilterColumn($filters['active']));
	Assert::null($grid->getFilterColumn($filters['tags']));
	Assert::same(['tags'], array_keys($grid->getFiltersWithoutColumn($filters)), 'neaktivní filtr bez sloupce je v Další filtry');

	$grid->setFilter(['tags' => ['a']]);
	Assert::same([], $grid->getFiltersWithoutColumn($filters), 'aktivní filtr bez sloupce má panel v čipu');
});

test('typy polí panelu', function () {
	$grid = columnFiltersGrid();
	$filters = $grid->getPanelFilters([], []);

	Assert::same('text', $grid->getFilterFieldType($filters['name']));
	Assert::same('daterange', $grid->getFilterFieldType($filters['orderDate']));
	Assert::same('select', $grid->getFilterFieldType($filters['state']));
	Assert::same('bool', $grid->getFilterFieldType($filters['active']));
	Assert::same('multiselect', $grid->getFilterFieldType($filters['tags']));
});

test('hodnoty čipů v pořadí sloupců', function () {
	$grid = columnFiltersGrid();
	$grid->setFilter([
		'tags' => ['a', 'b', 'c'],
		'state' => 'account',
		'orderDate' => ['from' => '2026-10-01', 'to' => '2026-11-30'],
		'name' => 'Vochomurka',
		'active' => '0',
	]);

	$values = chipValues($grid->getFilterChips($grid->getPanelFilters([], [])));
	Assert::same('„Vochomurka“', $values['name']);
	Assert::same('1. 10. – 30. 11. 2026', $values['orderDate']);
	Assert::same('Účet', $values['state'], 'popisek Html volby ze skupiny bez odsazení');
	Assert::same(['name', 'orderDate', 'state', 'active', 'tags'], array_keys($values));
	Assert::same('Ne', $values['active']);
	Assert::same('A, B +1', $values['tags']);
});

test('období přes dva roky a jen začátek', function () {
	$grid = columnFiltersGrid();
	$filter = $grid->getPanelFilters([], [])['orderDate'];

	Assert::same('20. 12. 2025 – 5. 1. 2026', $grid->formatFilterValue($filter, ['from' => '2025-12-20', 'to' => '2026-01-05']));
	// grid bez překladače vrací klíč „od“
	Assert::same('fcadmin.datagrid.columnFilters.from 1. 10. 2026', $grid->formatFilterValue($filter, ['from' => '2026-10-01', 'to' => '']));
});

test('výchozí filtr ve výchozí hodnotě čip nemá a aktivní není, změněný je běžný čip', function () {
	$grid = columnFiltersGrid();
	$grid->setDefaultFilter(['state' => 'done']);

	$grid->setFilter(['state' => 'done']);
	Assert::same([], $grid->getFilterChips($grid->getPanelFilters([], [])));
	Assert::false($grid->isFilterActive());

	$grid->setFilter(['state' => 'gate']);
	Assert::same(['state' => 'Brána'], chipValues($grid->getFilterChips($grid->getPanelFilters([], []))));
	Assert::true($grid->isFilterActive());
});

test('hodnota 0 je aktivní filtr', function () {
	$grid = columnFiltersGrid();
	$grid->setFilter(['active' => '0']);

	Assert::true($grid->isFilterActive());
	Assert::same(['active' => 'Ne'], chipValues($grid->getFilterChips($grid->getPanelFilters([], []))));
});

test('přepínač výchozího filtru: pole typu default, Vše bez čipu', function () {
	$grid = columnFiltersGrid();
	$grid->setDefaultFilter(['active' => 1]);
	$grid->addDefaultFilterToggle('active', 'Jen aktivní');
	$filter = $grid->getPanelFilters([], [])['active'];

	Assert::same('default', $grid->getFilterFieldType($filter));
	Assert::same(['active' => 'Jen aktivní'], $grid->getDefaultFilterToggles());
	Assert::same(1, $grid->getDefaultFilterValue('active'));

	Assert::same(['all', 1, 0], array_keys($filter->getOptions()), 'volba Vše je první, prázdná zmizí');

	$grid->setFilter(['active' => ColumnFiltersGrid::DEFAULT_FILTER_ALL]);
	Assert::true($grid->isDefaultFilterAll('active'));
	Assert::same([], $grid->getFilterChips($grid->getPanelFilters([], [])));
	Assert::false($grid->isFilterActive());
});

test('přepínač jde jen na filtr s výchozí hodnotou', function () {
	Assert::exception(fn() => columnFiltersGrid()->addDefaultFilterToggle('active', 'Jen aktivní'), LogicException::class);
});

test('nastavení sloupců: pořadí a šířka z úložiště, neznámé sloupce na konci', function () {
	$storage = new class implements ADT\FancyAdmin\UI\Components\Grids\GridColumnSettingsStorage {
		public array $saved = [];

		public function load(string $grid): array
		{
			return ['order' => ['state', 'nope', 'name'], 'widths' => ['state' => 'wide', 'name' => 'giant']];
		}

		public function save(string $grid, array $settings): void
		{
			$this->saved[$grid] = $settings;
		}
	};
	$grid = columnFiltersGrid();
	$grid->setColumnSettingsStorage($storage, 'Admin:Order:grid');

	Assert::same(['state', 'name', 'orderDate', 'isActive'], array_keys($grid->getColumns()));
	Assert::same('wide', $grid->getColumnWidth('state'));
	Assert::same('auto', $grid->getColumnWidth('name'), 'neznámá šířka je Auto');
	Assert::contains('fa-filters-col-wide', (string) $grid->getColumns()['state']->getElementForRender('th', 'state'));
	Assert::notContains('fa-filters-col', (string) $grid->getColumns()['name']->getElementForRender('td', 'name'));
});

test('filtr skrytého sloupce má panel v čipu', function () {
	$grid = columnFiltersGrid();
	$grid->setFilter(['name' => 'X', 'tags' => ['a']]);

	$chips = [];
	foreach ($grid->getFilterChips($grid->getPanelFilters([], [])) as $chip) {
		$chips[$chip->key] = $chip->hasOwnPanel();
	}

	Assert::same(['name' => false, 'tags' => true], $chips);
});
