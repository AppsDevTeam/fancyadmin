<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\CollapsibleFiltersTrait;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Tester\Assert;

/**
 * Tlačítko Filtry (CollapsibleFiltersTrait, DataGridFilters.latte, docs/datagrid-filters.md): šablona, kdy je filtr
 * aktivní (výchozí filtr ne) a které filtry se sbalují.
 */

require __DIR__ . '/bootstrap.php';

final class CollapsibleFiltersGrid extends DataGrid
{
	use CollapsibleFiltersTrait;
}

test('šablona gridu je DataGridFilters.latte, dědí ze šablony knihovny', function () {
	$grid = new CollapsibleFiltersGrid();

	Assert::same(CollapsibleFiltersGrid::COLLAPSIBLE_FILTERS_TEMPLATE, $grid->getOriginalTemplateFile());
	Assert::same(CollapsibleFiltersGrid::COLLAPSIBLE_FILTERS_TEMPLATE, $grid->getTemplateFile());
	Assert::same((new DataGrid())->getOriginalTemplateFile(), $grid->getCollapsibleFiltersParentTemplate());
});

test('šablona se přeloží', function () {
	$latte = new Engine();
	$latte->setTempDirectory(null);
	$latte->addExtension(new TranslatorExtension(null));

	Assert::noError(fn() => $latte->compile(CollapsibleFiltersGrid::COLLAPSIBLE_FILTERS_TEMPLATE));
});

function gridWithFilters(): CollapsibleFiltersGrid
{
	$grid = new CollapsibleFiltersGrid();
	$grid->addFilterSelect('state', 'Stav', ['' => 'Vše', 'waiting' => 'Čeká', 'approved' => 'Schváleno']);
	$grid->addFilterText('name', 'Jméno');
	$grid->addFilterMultiSelect('tags', 'Štítky', ['a' => 'A']);

	return $grid;
}

test('bez filtru a s výchozím filtrem není filtr aktivní, s vlastní hodnotou ano', function () {
	$grid = gridWithFilters();
	Assert::false($grid->isFilterActive());

	$grid->setDefaultFilter(['state' => 'waiting']);
	$grid->setFilter(['state' => 'waiting', 'name' => '']);
	Assert::true($grid->isFilterDefault());
	Assert::false($grid->isFilterActive());
	Assert::true($grid->isFilterActive('state'));

	$grid->setFilter(['state' => 'approved']);
	Assert::false($grid->isFilterDefault());
	Assert::true($grid->isFilterActive());

	$grid->setFilter(['state' => 'waiting', 'name' => 'Novák']);
	Assert::true($grid->isFilterActive());
});

test('grid bez výchozího filtru: prázdné hodnoty z formuláře nejsou aktivní filtr', function () {
	$grid = gridWithFilters();
	$grid->setFilter(['name' => '', 'tags' => []]);

	Assert::false($grid->isFilterDefault());
	Assert::false($grid->isFilterActive());
});

test('sbalují se filtry kromě hledání, aktivity, pokročilého hledání, přepínačů a období', function () {
	$grid = new CollapsibleFiltersGrid();

	Assert::false($grid->hasCollapsibleFilters([], [], []));
	Assert::false($grid->hasCollapsibleFilters(['search' => 1, 'isActive' => 1, 'advancedSearch' => 1, 'mine' => 1, 'period' => 1], ['mine' => true], ['period' => 1]));
	Assert::true($grid->hasCollapsibleFilters(['search' => 1, 'state' => 1], [], []));
});
