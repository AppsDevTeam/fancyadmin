<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

/**
 * Tlačítko „Filtry“, které sbalí řádek filtrů nad tabulkou (docs/datagrid-filters.md).
 * Patří do potomka ADT\Datagrid\Component\DataGrid. Filtry jsou sbalené, dokud uživatel
 * nefiltruje; výchozí filtr gridu se za aktivní nepočítá.
 */
trait CollapsibleFiltersTrait
{
	public const string COLLAPSIBLE_FILTERS_TEMPLATE = __DIR__ . '/DataGridFilters.latte';

	/** Šablona projektu i stromový grid dědí z `$originalTemplate`, tlačítko tak dostanou všechny. */
	public function getOriginalTemplateFile(): string
	{
		return self::COLLAPSIBLE_FILTERS_TEMPLATE;
	}

	/** Šablona knihovny, ze které dědí `DataGridFilters.latte`. */
	public function getCollapsibleFiltersParentTemplate(): string
	{
		return parent::getOriginalTemplateFile();
	}

	/**
	 * Má grid filtry, které se sbalují? Hledání, přepínače a období zůstávají v liště nad gridem.
	 *
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $switcherValues
	 * @param array<string, mixed> $periodFilters
	 */
	public function hasCollapsibleFilters(array $filters, array $switcherValues, array $periodFilters): bool
	{
		return (bool) array_diff(array_keys($filters), ['search', 'isActive', 'advancedSearch'], array_keys($switcherValues), array_keys($periodFilters));
	}

	/** Výchozí filtr není aktivní - sbalení filtrů ho obnoví (datagrid.js) a řádek by se hned znovu rozbalil. */
	public function isFilterActive(?string $filter = null): bool
	{
		if ($filter === null && $this->isFilterDefault()) {
			return false;
		}

		return parent::isFilterActive($filter);
	}

	/** Na rozdíl od knihovny přehlíží prázdné hodnoty, které formulář filtru posílá i nevyplněné. */
	public function isFilterDefault(): bool
	{
		$filled = static fn(array $filter): array => array_filter($filter, static fn($value) => $value !== null && $value !== '' && $value !== []);

		return $this->defaultFilter !== [] && $filled($this->filter) == $filled($this->defaultFilter);
	}
}
