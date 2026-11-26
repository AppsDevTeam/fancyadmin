<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

use ADT\Datagrid\Filter\FilterCheckbox;
use Closure;
use Contributte\Datagrid\Filter\Filter;
use Contributte\Datagrid\Filter\FilterDate;
use Contributte\Datagrid\Filter\FilterDateRange;
use Contributte\Datagrid\Filter\FilterMultiSelect;
use Contributte\Datagrid\Filter\FilterRange;
use Contributte\Datagrid\Filter\FilterSelect;
use Contributte\Datagrid\Filter\FilterText;
use Contributte\Datagrid\Filter\OneColumnFilter;
use DateTimeImmutable;

/**
 * Filtr v hlavičce sloupce (docs/datagrid-column-filters.md): klik na název sloupce otevře panel s polem filtru,
 * pod lištou je pruh aktivních filtrů s počtem a čipy. Patří do potomka ADT\Datagrid\Component\DataGrid místo
 * CollapsibleFiltersTrait. Filtr se odešle až tlačítkem Použít (šablona polím bere atributy autosubmitu knihovny), výchozí
 * filtr gridu se za aktivní nepočítá.
 */
trait ColumnFiltersTrait
{
	public const string COLUMN_FILTERS_TEMPLATE = __DIR__ . '/DataGridColumnFilters.latte';

	/** Klíče, které vykresluje lišta knihovny (hledání, přepínač aktivity, pokročilé hledání), ne panel. */
	private const array OUTSIDE_PANEL_FILTERS = ['search', 'isActive', 'advancedSearch'];

	/** Víc hodnot výběru čip vypíše jen do tohoto počtu, zbytek jako „+N“. */
	private const int CHIP_LISTED_VALUES = 2;

	private ?Closure $unfilteredCountCallback = null;

	private string $countTranslation = 'fcadmin.datagrid.columnFilters.count';

	public function getOriginalTemplateFile(): string
	{
		return self::COLUMN_FILTERS_TEMPLATE;
	}

	/** Šablona knihovny, ze které dědí `DataGridColumnFilters.latte`. */
	public function getColumnFiltersParentTemplate(): string
	{
		return parent::getOriginalTemplateFile();
	}

	/**
	 * Počet záznamů bez filtrů do „X z Y“. Grid ho nezná (datasource počítá už vyfiltrované),
	 * proto ho dodá jeho vlastník, typicky novým query objektem bez filtrů.
	 *
	 * @param (Closure(): int)|null $callback
	 */
	public function setUnfilteredCountCallback(?Closure $callback): static
	{
		$this->unfilteredCountCallback = $callback;

		return $this;
	}

	public function getUnfilteredCount(): ?int
	{
		return $this->unfilteredCountCallback ? ($this->unfilteredCountCallback)() : null;
	}

	/** Počet vyfiltrovaných záznamů - stránkovaný grid ho má v paginatoru, jinak jsou to vykreslené řádky. */
	public function getFilteredCount(int $renderedRows): int
	{
		return $this->isPaginated() ? $this['paginator']->getPaginator()->getItemCount() ?? $renderedRows : $renderedRows;
	}

	/** Překlad počtu s množným číslem a parametrem `%formatted%`, např. „%formatted% objednávek“. */
	public function setCountTranslation(string $key): static
	{
		$this->countTranslation = $key;

		return $this;
	}

	public function getCountTranslation(): string
	{
		return $this->countTranslation;
	}

	/**
	 * Filtry, které patří do panelů (ne do lišty knihovny).
	 *
	 * @param array<string, mixed> $switcherValues
	 * @param array<string, mixed> $periodFilters
	 * @return array<string, Filter>
	 */
	public function getPanelFilters(array $switcherValues, array $periodFilters): array
	{
		return array_diff_key($this->filters, array_flip(self::OUTSIDE_PANEL_FILTERS), $switcherValues, $periodFilters);
	}

	/** Sloupec, v jehož hlavičce je panel filtru: stejný klíč, jinak sloupec, nad kterým filtr hledá. */
	public function getFilterColumn(Filter $filter): ?string
	{
		if (isset($this->columns[$filter->getKey()])) {
			return $filter->getKey();
		}

		if ($filter instanceof OneColumnFilter && isset($this->columns[$filter->getColumn()])) {
			return $filter->getColumn();
		}

		return null;
	}

	/** Filtr, jehož panel je v hlavičce viditelného sloupce. */
	public function getColumnFilter(string $columnKey, array $panelFilters): ?Filter
	{
		foreach ($panelFilters as $filter) {
			if ($this->getFilterColumn($filter) === $columnKey) {
				return $filter;
			}
		}

		return null;
	}

	public function isColumnVisible(string $columnKey): bool
	{
		return isset($this->getColumns()[$columnKey]);
	}

	/**
	 * Neaktivní filtry bez sloupce v gridu - nemají hlavičku, kterou by šly otevřít, nabídne je tlačítko Další filtry.
	 * Neaktivní filtr skrytého sloupce se nenabízí, nastaví se až po zobrazení sloupce.
	 *
	 * @param array<string, Filter> $panelFilters
	 * @return array<string, Filter>
	 */
	public function getFiltersWithoutColumn(array $panelFilters): array
	{
		return array_filter($panelFilters, fn(Filter $filter): bool => $this->getFilterColumn($filter) === null && !$this->hasFilterValue($filter->getKey()));
	}

	/**
	 * Čipy filtrů s hodnotou v pořadí sloupců gridu, filtry bez sloupce na konci. Výchozí hodnota výchozího
	 * filtru je čip bez křížku.
	 *
	 * @param array<string, Filter> $panelFilters
	 * @return list<FilterChip>
	 */
	public function getFilterChips(array $panelFilters): array
	{
		$order = array_flip(array_keys($this->columns));
		$chips = [];
		foreach ($panelFilters as $key => $filter) {
			if (!$this->hasFilterValue($key)) {
				continue;
			}

			$value = $this->formatFilterValue($filter, $this->filter[$key]);
			if ($value === null) {
				continue;
			}

			$column = $this->getFilterColumn($filter);
			$chips[] = new FilterChip(
				$key,
				$this->getFilterLabel($filter),
				$value,
				$this->isDefaultFilterValue($key),
				$column,
				$column !== null && $this->isColumnVisible($column),
			);
		}

		$position = static fn(FilterChip $chip): int => $chip->column !== null ? $order[$chip->column] ?? PHP_INT_MAX : PHP_INT_MAX;
		usort($chips, static fn(FilterChip $a, FilterChip $b): int => $position($a) <=> $position($b));

		return $chips;
	}

	/** Název filtru: překladový klíč, nebo Html (např. s ikonou) jako holý text. */
	public function getFilterLabel(Filter $filter): string
	{
		$name = $filter->getName();

		return is_string($name) ? $this->getTranslator()->translate($name) : self::plainText($name);
	}

	/** Popisek volby výběru: překladový klíč, nebo Html jako holý text. */
	public function getOptionLabel(mixed $label): string
	{
		return is_string($label) ? $this->getTranslator()->translate($label) : self::plainText($label);
	}

	/** Hodnota filtru pro čtečku v hlavičce sloupce, null bez hodnoty. */
	public function getFilterValueLabel(Filter $filter): ?string
	{
		return $this->hasFilterValue($filter->getKey()) ? $this->formatFilterValue($filter, $this->filter[$filter->getKey()]) : null;
	}

	public function hasFilterValue(string $key): bool
	{
		return self::isFilled($this->filter[$key] ?? null);
	}

	public function isDefaultFilterValue(string $key): bool
	{
		return array_key_exists($key, $this->defaultFilter)
			&& self::isFilled($this->defaultFilter[$key])
			&& self::normalize($this->filter[$key] ?? null) == self::normalize($this->defaultFilter[$key]);
	}

	/** Typ pole v panelu: text, select, multiselect, bool (výběr Ano/Ne), date, daterange, range, checkbox. */
	public function getFilterFieldType(Filter $filter): string
	{
		return match (true) {
			$filter instanceof FilterMultiSelect => 'multiselect',
			$filter instanceof FilterSelect => $this->isBoolSelect($filter) ? 'bool' : 'select',
			$filter instanceof FilterDateRange => 'daterange',
			$filter instanceof FilterDate => 'date',
			$filter instanceof FilterRange => 'range',
			$filter instanceof FilterCheckbox => 'checkbox',
			default => 'text',
		};
	}

	/**
	 * Hodnota čipu: text v uvozovkách, popisky voleb, období „1. 10. – 30. 11. 2026“.
	 */
	public function formatFilterValue(Filter $filter, mixed $value): ?string
	{
		$translator = $this->getTranslator();

		if ($filter instanceof FilterSelect) {
			$options = self::flattenOptions($filter->getOptions());
			$labels = [];
			foreach (is_array($value) ? $value : [$value] as $_value) {
				if (!self::isFilled($_value)) {
					continue;
				}
				$label = $options[$_value] ?? (string) $_value;
				$labels[] = $filter->getTranslateOptions() && is_string($label) ? $translator->translate($label) : self::plainText($label);
			}

			if ($labels === []) {
				return null;
			}

			$listed = array_slice($labels, 0, self::CHIP_LISTED_VALUES);
			$rest = count($labels) - count($listed);

			return implode(', ', $listed) . ($rest > 0 ? ' +' . $rest : '');
		}

		if ($filter instanceof FilterDateRange || $filter instanceof FilterRange) {
			$from = self::isFilled($value['from'] ?? null) ? (string) $value['from'] : null;
			$to = self::isFilled($value['to'] ?? null) ? (string) $value['to'] : null;

			if ($filter instanceof FilterDateRange) {
				return self::formatDateRange(self::parseDate($from), self::parseDate($to), $from, $to);
			}

			return match (true) {
				$from !== null && $to !== null => $from . ' – ' . $to,
				$from !== null => $translator->translate('fcadmin.datagrid.columnFilters.from') . ' ' . $from,
				$to !== null => $translator->translate('fcadmin.datagrid.columnFilters.to') . ' ' . $to,
				default => null,
			};
		}

		if ($filter instanceof FilterDate) {
			$date = self::parseDate((string) $value);

			return $date ? $date->format('j. n. Y') : (string) $value;
		}

		if ($filter instanceof FilterCheckbox) {
			return $translator->translate('fcadmin.datagrid.columnFilters.yes');
		}

		if ($filter instanceof FilterText) {
			return '„' . $value . '“';
		}

		return is_scalar($value) ? (string) $value : null;
	}

	/** Výchozí filtr se za aktivní nepočítá - grid s ním je výchozí stav, ne vyfiltrovaný. */
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
		$filled = static fn(array $filter): array => array_filter($filter, self::isFilled(...));

		return $this->defaultFilter !== [] && $filled($this->filter) == $filled($this->defaultFilter);
	}

	/**
	 * Knihovna po filtrování překreslí jen tělo tabulky. Pruh s čipy a počtem a hlavička s panely ukazují hodnoty
	 * filtrů, proto se překreslí taky.
	 *
	 * @param string[] $snippets
	 */
	public function reload(array $snippets = []): void
	{
		parent::reload([...$snippets, 'columnFilters', 'table']);
	}

	/** Křížek u čipu výchozího filtru, který uživatel změnil, vrací výchozí hodnotu - knihovna by filtr vyprázdnila. */
	public function handleResetColumnFilter(string $key): void
	{
		if (!array_key_exists($key, $this->defaultFilter)) {
			parent::handleResetColumnFilter($key);

			return;
		}

		$this->filter[$key] = $this->defaultFilter[$key];
		$storedFilters = $this->getStorageData('_grid_filters', []);
		$storedFilters[$key] = $this->defaultFilter[$key];
		$this->saveStorageData('_grid_filters', $storedFilters);

		$this->reloadTheWholeGrid();
	}

	/** Výběr, jehož volby (bez „vše“) jsou jen 1 a 0, kreslí panel jako přepínač Vše / Ano / Ne. */
	private function isBoolSelect(FilterSelect $filter): bool
	{
		$keys = array_map('strval', array_keys(array_filter($filter->getOptions(), static fn($label, $key): bool => $key !== '', ARRAY_FILTER_USE_BOTH)));
		sort($keys);

		return $keys === ['0', '1'];
	}

	/**
	 * Volby ze skupin (optgroup) do jedné úrovně.
	 *
	 * @param array<int|string, mixed> $options
	 * @return array<int|string, mixed>
	 */
	private static function flattenOptions(array $options): array
	{
		$flat = [];
		foreach ($options as $key => $label) {
			if (is_array($label)) {
				$flat += self::flattenOptions($label);
			} else {
				$flat[$key] = $label;
			}
		}

		return $flat;
	}

	/** Popisek volby může být Html (odsazení skupin přes &nbsp;), čip potřebuje holý text. */
	private static function plainText(mixed $label): string
	{
		$text = html_entity_decode(strip_tags((string) $label), ENT_QUOTES | ENT_HTML5);

		return trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $text));
	}

	private static function isFilled(mixed $value): bool
	{
		if (is_array($value)) {
			return array_filter($value, self::isFilled(...)) !== [];
		}

		return $value !== null && $value !== '';
	}

	private static function normalize(mixed $value): mixed
	{
		return is_array($value) ? array_filter(array_map('strval', $value), static fn(string $item): bool => $item !== '') : (string) $value;
	}

	private static function parseDate(?string $value): ?DateTimeImmutable
	{
		if ($value === null) {
			return null;
		}

		foreach (['!Y-m-d', '!d.m.Y', '!j.n.Y'] as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $value);
			if ($date !== false) {
				return $date;
			}
		}

		return null;
	}

	private function formatDateRange(?DateTimeImmutable $from, ?DateTimeImmutable $to, ?string $rawFrom, ?string $rawTo): ?string
	{
		$translator = $this->getTranslator();

		if ($from && $to) {
			return ($from->format('Y') === $to->format('Y') ? $from->format('j. n.') : $from->format('j. n. Y')) . ' – ' . $to->format('j. n. Y');
		}

		if ($from || $rawFrom !== null) {
			return $translator->translate('fcadmin.datagrid.columnFilters.from') . ' ' . ($from?->format('j. n. Y') ?? $rawFrom);
		}

		if ($to || $rawTo !== null) {
			return $translator->translate('fcadmin.datagrid.columnFilters.to') . ' ' . ($to?->format('j. n. Y') ?? $rawTo);
		}

		return null;
	}
}
