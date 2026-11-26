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

test('výchozí filtr je čip bez křížku, změněný běžný čip', function () {
	$grid = columnFiltersGrid();
	$grid->setDefaultFilter(['state' => 'done']);

	$grid->setFilter(['state' => 'done']);
	[$chip] = $grid->getFilterChips($grid->getPanelFilters([], []));
	Assert::true($chip->default);
	Assert::false($grid->isFilterActive());

	$grid->setFilter(['state' => 'gate']);
	[$chip] = $grid->getFilterChips($grid->getPanelFilters([], []));
	Assert::false($chip->default);
	Assert::true($grid->isFilterActive());
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
