<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

use ADT\Datagrid\Filter\FilterCheckbox;
use Closure;
use Contributte\Datagrid\Column\Column;
use Contributte\Datagrid\Filter\Filter;
use Contributte\Datagrid\Filter\FilterDate;
use Contributte\Datagrid\Filter\FilterDateRange;
use Contributte\Datagrid\Filter\FilterMultiSelect;
use Contributte\Datagrid\Filter\FilterRange;
use Contributte\Datagrid\Filter\FilterSelect;
use Contributte\Datagrid\Filter\FilterText;
use Contributte\Datagrid\Filter\OneColumnFilter;
use DateTimeImmutable;
use Nette\Application\Attributes\Persistent;
use Nette\Utils\Validators;

/**
 * Filtr v hlavičce sloupce (docs/datagrid-column-filters.md): klik na název sloupce otevře panel s polem filtru,
 * pod lištou je pruh aktivních filtrů s počtem a čipy. Patří do potomka ADT\Datagrid\Component\DataGrid místo
 * CollapsibleFiltersTrait. Filtr se odešle až tlačítkem Použít (šablona polím bere atributy autosubmitu knihovny), výchozí
 * filtr gridu ve výchozí hodnotě se za aktivní nepočítá a čip nemá. Výchozí filtr ovládaný přepínačem v liště
 * přidá addDefaultFilterToggle().
 */
trait ColumnFiltersTrait
{
	public const string COLUMN_FILTERS_TEMPLATE = __DIR__ . '/DataGridColumnFilters.latte';

	/** Hledání ve viditelných sloupcích (pole v liště). */
	public const string FULLTEXT_FILTER = 'fulltext';

	/** Kratší dotaz by vrátil skoro všechno. */
	public const int FULLTEXT_MIN_LENGTH = 2;

	/** Klíče, které vykresluje lišta (hledání, přepínač aktivity, pokročilé hledání), ne panel. */
	private const array OUTSIDE_PANEL_FILTERS = ['search', 'isActive', 'advancedSearch', self::FULLTEXT_FILTER];

	/**
	 * Hodnota „Vše“ výchozího filtru s přepínačem. Prázdná hodnota by z URL zmizela a grid (stav nese jen URL)
	 * by při dalším načtení použil zase výchozí filtr.
	 */
	public const string DEFAULT_FILTER_ALL = 'all';

	public const string COLUMN_WIDTH_NARROW = 'narrow';
	public const string COLUMN_WIDTH_AUTO = 'auto';
	public const string COLUMN_WIDTH_WIDE = 'wide';

	/** Klíče skrytých sloupců knihovny, které trait drží v nastavení sloupců místo session. */
	private const array HIDDEN_COLUMNS_KEYS = ['_grid_hidden_columns', '_grid_hidden_columns_manipulated'];

	private const string COLUMN_SETTINGS_STATE_KEY = '_grid_column_settings';

	/** Víc hodnot výběru čip vypíše jen do tohoto počtu, zbytek jako „+N“. */
	private const int CHIP_LISTED_VALUES = 2;

	private ?Closure $unfilteredCountCallback = null;

	private string $countTranslation = 'fcadmin.datagrid.columnFilters.count';

	/** @var array<string, string> klíč výchozího filtru => popisek přepínače */
	private array $defaultFilterToggles = [];

	/** Sloupec, který se objevil vypnutím přepínače - šablona jeho buňky krátce podbarví. */
	private ?string $revealedColumn = null;

	private ?GridColumnSettingsStorage $columnSettingsStorage = null;

	/** Uložená sestava, kterou uživatel vybral; jen kvůli štítku ve vyhledávání, filtry nese URL jako vždy. */
	#[Persistent]
	public ?int $preset = null;

	private ?GridPresetStorage $presetStorage = null;

	private string $presetKey = '';

	/** @var list<GridPreset>|null */
	private ?array $presets = null;

	/** @var (Closure(string $format, bool $visibleOnly, ?string $email): void)|null */
	private ?Closure $exportHandler = null;

	private ?string $exportEmail = null;

	private string $columnSettingsKey = '';

	/** @var array<string, mixed>|null */
	private ?array $columnSettings = null;

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
	 * Hledání ve viditelných sloupcích: textové filtry sloupců, které jsou vidět, spojené přes NEBO. Jak hledat
	 * jeden filtr a jak výsledky spojit, ví jen vlastník gridu (query objekt), proto `$callback(mixed $query,
	 * string $value, list<FilterText> $filters)`.
	 */
	public function addFilterFulltext(Closure $callback): FilterText
	{
		$filter = $this->addFilterText(self::FULLTEXT_FILTER, 'fcadmin.datagrid.columnFilters.search');
		$filter->setCondition(function (mixed $query, mixed $value) use ($callback): void {
			$value = trim((string) $value);
			if (mb_strlen($value) >= self::FULLTEXT_MIN_LENGTH) {
				$callback($query, $value, $this->getFulltextFilters());
			}
		});

		return $filter;
	}

	/** Hledání dává smysl gridu s textovým filtrem, který ještě nemá hledání svoje ani knihovny. */
	public function canAddFulltext(): bool
	{
		if ($this->hasFulltext() || isset($this->filters['search'])) {
			return false;
		}

		return array_filter($this->filters, static fn(Filter $filter): bool => $filter instanceof FilterText) !== [];
	}

	public function hasFulltext(): bool
	{
		return isset($this->filters[self::FULLTEXT_FILTER]);
	}

	/**
	 * Textové filtry viditelných sloupců, ve kterých hledá pole v liště.
	 *
	 * @return list<FilterText>
	 */
	public function getFulltextFilters(): array
	{
		$filters = [];
		foreach ($this->filters as $key => $filter) {
			if ($key === self::FULLTEXT_FILTER || !$filter instanceof FilterText) {
				continue;
			}

			$column = $this->getFilterColumn($filter);
			if ($column !== null && $this->isColumnVisible($column)) {
				$filters[] = $filter;
			}
		}

		return $filters;
	}

	/**
	 * Výchozí filtr (setDefaultFilter) jako přepínač v liště, pojmenovaný podle výchozí hodnoty („Jen aktivní“).
	 * Vypnutí nastaví „Vše“ (DEFAULT_FILTER_ALL), zapnutí vrátí výchozí. Dokud platí výchozí hodnota, řídí se
	 * viditelnost sloupce filtru nastavením sloupců; jiná hodnota sloupec zobrazí vždy. Volá se po setDefaultFilter().
	 */
	public function addDefaultFilterToggle(string $key, string $label): static
	{
		if (!array_key_exists($key, $this->defaultFilter)) {
			throw new \LogicException("Filtr '$key' nemá výchozí hodnotu, přepínač by neměl co zapnout.");
		}

		$filter = $this->filters[$key];
		if (!$filter instanceof FilterSelect) {
			throw new \LogicException("Přepínač umí jen výběrový filtr, '$key' jím není.");
		}

		$this->defaultFilterToggles[$key] = $label;
		$all = [self::DEFAULT_FILTER_ALL => 'fcadmin.datagrid.columnFilters.all'];
		(fn() => $this->options = $all + array_diff_key($this->options, ['' => true]))->call($filter);

		return $this;
	}

	/** @return array<string, string> klíč filtru => popisek přepínače */
	public function getDefaultFilterToggles(): array
	{
		return $this->defaultFilterToggles;
	}

	public function isDefaultFilterAll(string $key): bool
	{
		return isset($this->defaultFilterToggles[$key]) && ($this->filter[$key] ?? null) === self::DEFAULT_FILTER_ALL;
	}

	/** „Vše“ výchozího filtru knihovně předá jako žádnou hodnotu, dotaz se pak podle filtru neomezí. */
	public function assembleFilters(): array
	{
		$all = array_filter(array_keys($this->defaultFilterToggles), $this->isDefaultFilterAll(...));
		foreach ($all as $key) {
			$this->filter[$key] = null;
		}

		try {
			return parent::assembleFilters();
		} finally {
			foreach ($all as $key) {
				$this->filter[$key] = self::DEFAULT_FILTER_ALL;
			}
		}
	}

	public function getDefaultFilterValue(string $key): mixed
	{
		return $this->defaultFilter[$key] ?? null;
	}

	public function isDefaultFilterToggle(string $key): bool
	{
		return isset($this->defaultFilterToggles[$key]);
	}

	public function getRevealedColumn(): ?string
	{
		return $this->revealedColumn;
	}

	public function handleToggleDefaultFilter(string $key, bool $on = false): void
	{
		if (!isset($this->defaultFilterToggles[$key])) {
			$this->getPresenter()->error();
		}

		$filter = $this->filters[$key];
		$column = $this->getFilterColumn($filter);
		$hiddenBySettings = $column !== null && !isset(parent::getColumns()[$column]);

		$value = $on ? $this->defaultFilter[$key] : self::DEFAULT_FILTER_ALL;
		$this->filter[$key] = $value;
		$storedFilters = $this->getStorageData('_grid_filters', []);
		$storedFilters[$key] = $value;
		$this->saveStorageData('_grid_filters', $storedFilters);
		$this->saveStorageData('_grid_has_filtered', 1);
		$this->deleteStorageData('_grid_page');
		$this->page = 1;

		if (!$on && $hiddenBySettings) {
			$this->revealedColumn = $column;
		}

		$this->reloadTheWholeGrid();
	}

	/**
	 * Viditelné sloupce v pořadí z nastavení sloupců, s třídou šířky. Sloupec výchozího filtru s jinou než výchozí
	 * hodnotou je vidět i skrytý v nastavení.
	 */
	public function getColumns(): array
	{
		$columns = parent::getColumns();
		foreach (array_keys($this->defaultFilterToggles) as $key) {
			$column = isset($this->filters[$key]) ? $this->getFilterColumn($this->filters[$key]) : null;
			if ($column !== null && !isset($columns[$column]) && !$this->isDefaultFilterValue($key)) {
				$columns[$column] = $this->columns[$column];
			}
		}

		$this->applyColumnWidths();

		return array_intersect_key($this->getOrderedColumns(), $columns);
	}

	/**
	 * Úložiště nastavení sloupců u uživatele; `$key` grid jednoznačně určí (presenter a komponenta).
	 */
	public function setColumnSettingsStorage(GridColumnSettingsStorage $storage, string $key): static
	{
		$this->columnSettingsStorage = $storage;
		$this->columnSettingsKey = $key;
		$this->columnSettings = null;

		return $this;
	}

	/** @return array<string, mixed> */
	public function getColumnSettings(): array
	{
		if ($this->columnSettings === null) {
			$this->columnSettings = $this->columnSettingsStorage
				? $this->columnSettingsStorage->load($this->columnSettingsKey)
				: (array) ($this->getStateStorage()->loadState(self::COLUMN_SETTINGS_STATE_KEY) ?? []);
		}

		return $this->columnSettings;
	}

	/** @param array<string, mixed> $settings */
	private function saveColumnSettings(array $settings): void
	{
		$this->columnSettings = $settings;
		if ($this->columnSettingsStorage) {
			$this->columnSettingsStorage->save($this->columnSettingsKey, $settings);
		} else {
			$this->getStateStorage()->saveState(self::COLUMN_SETTINGS_STATE_KEY, $settings);
		}
	}

	/** Skryté sloupce knihovny patří do nastavení sloupců (u uživatele), ne do session. */
	public function getStorageData(string $key, mixed $defaultValue = null): mixed
	{
		if (in_array($key, self::HIDDEN_COLUMNS_KEYS, true)) {
			return $this->getColumnSettings()[$key] ?? $defaultValue;
		}

		return parent::getStorageData($key, $defaultValue);
	}

	public function saveStorageData(string $key, mixed $value): void
	{
		if (in_array($key, self::HIDDEN_COLUMNS_KEYS, true)) {
			$this->saveColumnSettings([$key => $value] + $this->getColumnSettings());

			return;
		}

		parent::saveStorageData($key, $value);
	}

	public function deleteStorageData(string $key): void
	{
		if (in_array($key, self::HIDDEN_COLUMNS_KEYS, true)) {
			$settings = $this->getColumnSettings();
			unset($settings[$key]);
			$this->saveColumnSettings($settings);

			return;
		}

		parent::deleteStorageData($key);
	}

	/**
	 * Všechny sloupce v pořadí z nastavení; sloupce, které nastavení nezná (přibyly později), jdou na konec.
	 *
	 * @return array<string, Column>
	 */
	public function getOrderedColumns(): array
	{
		$order = array_values(array_filter((array) ($this->getColumnSettings()['order'] ?? []), fn($key): bool => is_string($key) && isset($this->columns[$key])));

		return array_replace(array_fill_keys($order, null), $this->columns);
	}

	/** Název sloupce jako holý text (dialog Sloupce, popisky pro čtečku). */
	public function getColumnTitle(string $key): string
	{
		$column = $this->columns[$key];
		$name = $column->getName();

		return $column->isTranslatableHeader() ? $this->getTranslator()->translate($name) : self::plainText($name);
	}

	public function getColumnWidth(string $key): string
	{
		$width = $this->getColumnSettings()['widths'][$key] ?? self::COLUMN_WIDTH_AUTO;

		return in_array($width, [self::COLUMN_WIDTH_NARROW, self::COLUMN_WIDTH_WIDE], true) ? $width : self::COLUMN_WIDTH_AUTO;
	}

	/** Viditelnost podle nastavení sloupců (bez sloupců, které zobrazil výchozí filtr). */
	public function isColumnHiddenBySettings(string $key): bool
	{
		return !isset(parent::getColumns()[$key]);
	}

	/**
	 * Uložení dialogu Sloupce: POST `columns` = JSON seznam {key, visible, width} v novém pořadí.
	 */
	public function handleSaveColumns(): void
	{
		if (!$this->canHideColumns()) {
			$this->getPresenter()->error();
		}

		$posted = json_decode((string) $this->getPresenter()->getRequest()->getPost('columns'), true);
		if (!is_array($posted)) {
			$this->getPresenter()->error(httpCode: 400);
		}

		$order = [];
		$hidden = [];
		$widths = [];
		foreach ($posted as $item) {
			$key = is_array($item) ? ($item['key'] ?? null) : null;
			if (!is_string($key) || !isset($this->columns[$key]) || in_array($key, $order, true)) {
				continue;
			}

			$order[] = $key;
			if (empty($item['visible'])) {
				$hidden[] = $key;
			}
			if (in_array($item['width'] ?? null, [self::COLUMN_WIDTH_NARROW, self::COLUMN_WIDTH_WIDE], true)) {
				$widths[$key] = $item['width'];
			}
		}

		$this->saveColumnSettings([
			'order' => $order,
			'widths' => $widths,
			'_grid_hidden_columns' => $hidden,
			'_grid_hidden_columns_manipulated' => true,
		] + $this->getColumnSettings());

		$this->reloadTheWholeGrid();
	}

	/**
	 * Knihovna při prvním čtení skrytých sloupců doplní výchozí skryté (`setDefaultHide()`). Signál bez předchozího
	 * čtení by uložil prázdný seznam a zobrazil i ostatní výchozí skryté sloupce.
	 */
	public function handleShowColumn(string $column): void
	{
		parent::getColumns();
		parent::handleShowColumn($column);
	}

	public function handleHideColumn(string $column): void
	{
		parent::getColumns();
		parent::handleHideColumn($column);
	}

	public const array EXPORT_FORMATS = ['xlsx', 'csv'];

	/**
	 * Panel Export místo tlačítek exportů knihovny: formát, jen zobrazené sloupce a volitelně odeslání na e-mail
	 * (`$email` předvyplní pole). Data a soubor připraví vlastník gridu v `$handler`.
	 *
	 * @param Closure(string $format, bool $visibleOnly, ?string $email): void $handler
	 */
	public function setExportPanel(Closure $handler, ?string $email = null): static
	{
		$this->exportHandler = $handler;
		$this->exportEmail = $email;

		return $this;
	}

	public function hasExportPanel(): bool
	{
		return $this->exportHandler !== null;
	}

	public function getExportEmail(): ?string
	{
		return $this->exportEmail;
	}

	/**
	 * Stažení (GET s parametry signálu) nebo odeslání na e-mail (POST `format`, `visible`, `email` přes AJAX).
	 * Neplatnou adresu vrátí JS k poli (`faFiltersExportError`).
	 */
	public function handleExportGrid(string $format = 'xlsx', bool $visible = true): void
	{
		$presenter = $this->getPresenter();
		if (!$this->exportHandler) {
			$presenter->error();
		}

		$post = $presenter->getRequest()->getPost();
		$format = (string) ($post['format'] ?? $format);
		$visible = (bool) ($post['visible'] ?? $visible);
		if (!in_array($format, self::EXPORT_FORMATS, true)) {
			$presenter->error('', 400);
		}

		$email = isset($post['email']) ? trim((string) $post['email']) : null;
		if ($email !== null && !Validators::isEmail($email)) {
			$presenter->payload->faFiltersExportError = $this->getTranslator()->translate('fcadmin.datagrid.columnFilters.export.invalidEmail');
			$presenter->sendPayload();
		}

		($this->exportHandler)($format, $visible, $email);
	}

	/** Uložené sestavy gridu u uživatele; `$key` grid jednoznačně určí (presenter a komponenta). */
	public function setPresetStorage(GridPresetStorage $storage, string $key): static
	{
		$this->presetStorage = $storage;
		$this->presetKey = $key;
		$this->presets = null;

		return $this;
	}

	/** Sestavy mají smysl gridu, který jde filtrovat nebo mu jde nastavit sloupce. */
	public function hasPresets(): bool
	{
		return $this->presetStorage !== null && ($this->getPanelFilters([], []) !== [] || $this->canHideColumns());
	}

	/** @return list<GridPreset> */
	public function getPresets(): array
	{
		return $this->presets ??= $this->presetStorage?->findAll($this->presetKey) ?? [];
	}

	public function getActivePreset(): ?GridPreset
	{
		foreach ($this->preset !== null ? $this->getPresets() : [] as $preset) {
			if ($preset->id === $this->preset) {
				return $preset;
			}
		}

		return null;
	}

	/** Filtry nebo sloupce se od vybrané sestavy liší („· upraveno“). */
	public function isActivePresetModified(): bool
	{
		$preset = $this->getActivePreset();
		if (!$preset) {
			return false;
		}

		return self::normalizeFilters($preset->filters) != $this->getPresetFilters()
			|| ($preset->columns !== [] && self::normalizeColumns($preset->columns) != self::normalizeColumns($this->getPresetColumns()));
	}

	/**
	 * Filtry, které se ukládají do sestavy: všechny vyplněné kromě hledání (to se neukládá).
	 *
	 * @return array<string, mixed>
	 */
	public function getPresetFilters(): array
	{
		return self::normalizeFilters($this->filter);
	}

	/** @return array<string, mixed> */
	public function getPresetColumns(): array
	{
		if ($this->canHideColumns()) {
			// výchozí skryté sloupce knihovna doplní do nastavení až při prvním čtení
			parent::getColumns();
		}

		return $this->canHideColumns()
			? array_intersect_key($this->getColumnSettings(), array_flip(['order', 'widths', ...self::HIDDEN_COLUMNS_KEYS]))
			: [];
	}

	public function handleApplyPreset(int $id): void
	{
		$preset = $this->presetStorage?->find($this->presetKey, $id) ?? $this->getPresenter()->error();

		$this->setFilter($preset->filters);
		if ($preset->columns !== [] && $this->canHideColumns()) {
			$this->saveColumnSettings($preset->columns + $this->getColumnSettings());
		}
		$this->preset = $preset->id;
		$this->page = 1;

		$this->reloadTheWholeGrid();
	}

	/** Křížek na štítku sestavy: grid se vrátí do výchozího stavu (výchozí filtry), sloupce zůstanou. */
	public function handleClearPreset(): void
	{
		$this->preset = null;

		$this->handleResetFilter();
	}

	/**
	 * Uložení sestavy z panelu Uložit sestavu: POST `name` + `shared` (nová), nebo `overwrite` = ID vlastní sestavy,
	 * do které se uloží současné filtry a sloupce.
	 */
	public function handleSavePreset(): void
	{
		$request = $this->getPresenter()->getRequest();
		$overwrite = (int) $request->getPost('overwrite');

		$preset = $this->runPresetChange(function () use ($request, $overwrite): GridPreset {
			if ($overwrite) {
				$current = $this->presetStorage?->find($this->presetKey, $overwrite) ?? throw new GridPresetException('', GridPresetException::NOT_FOUND);

				return $this->presetStorage->update($this->presetKey, $current->id, $current->name, $current->shared, $this->getPresetFilters(), $this->getPresetColumns());
			}

			return $this->presetStorage->create($this->presetKey, trim((string) $request->getPost('name')), (bool) $request->getPost('shared'), $this->getPresetFilters(), $this->getPresetColumns());
		});

		$this->preset = $preset->id;
		$this->getPresenter()->flashMessageSuccess('fcadmin.datagrid.columnFilters.presets.saved');
		$this->reloadTheWholeGrid();
	}

	/** Úprava názvu a viditelnosti sestavy v seznamu sestav: POST `id`, `name`, `shared`. */
	public function handleUpdatePreset(): void
	{
		$request = $this->getPresenter()->getRequest();

		$this->runPresetChange(fn(): GridPreset => $this->presetStorage->update(
			$this->presetKey,
			(int) $request->getPost('id'),
			trim((string) $request->getPost('name')),
			(bool) $request->getPost('shared'),
		));

		$this->reloadTheWholeGrid();
	}

	public function handleDeletePreset(int $id): void
	{
		$this->runPresetChange(function () use ($id): void {
			$this->presetStorage->delete($this->presetKey, $id);
		});

		if ($this->preset === $id) {
			$this->preset = null;
		}
		$this->getPresenter()->flashMessageSuccess('fcadmin.datagrid.columnFilters.presets.deleted');
		$this->reloadTheWholeGrid();
	}

	/**
	 * Zakázaná nebo neexistující sestava je chyba požadavku, neplatný název se vrátí JS k poli (`faFiltersPresetError`).
	 *
	 * @template T
	 * @param callable(): T $change
	 * @return T
	 */
	private function runPresetChange(callable $change): mixed
	{
		if (!$this->presetStorage) {
			$this->getPresenter()->error();
		}

		try {
			$result = $change();
		} catch (GridPresetException $e) {
			if ($e->getCode() !== GridPresetException::INVALID) {
				$this->getPresenter()->error('', $e->getCode());
			}

			$presenter = $this->getPresenter();
			$presenter->payload->faFiltersPresetError = $this->getTranslator()->translate($e->getMessage());
			$presenter->sendPayload();
		}

		$this->presets = null;

		return $result;
	}

	/** @return array<string, mixed> */
	private static function normalizeFilters(array $filters): array
	{
		$normalized = [];
		foreach ($filters as $key => $value) {
			if ($key === self::FULLTEXT_FILTER || !self::isFilled($value)) {
				continue;
			}
			$normalized[(string) $key] = is_array($value)
				? array_map(static fn($item) => is_array($item) ? $item : (string) $item, array_filter($value, self::isFilled(...)))
				: (string) $value;
		}
		ksort($normalized);

		return $normalized;
	}

	/** @return array<string, mixed> */
	private static function normalizeColumns(array $columns): array
	{
		$hidden = array_values((array) ($columns['_grid_hidden_columns'] ?? []));
		sort($hidden);
		$widths = (array) ($columns['widths'] ?? []);
		ksort($widths);

		return ['order' => array_values((array) ($columns['order'] ?? [])), 'hidden' => $hidden, 'widths' => $widths];
	}

	/** Třída šířky na buňkách hlavičky i těla; prototyp sloupce se mění, proto se stará třída nejdřív odebere. */
	private function applyColumnWidths(): void
	{
		$classes = ['fa-filters-col-' . self::COLUMN_WIDTH_NARROW, 'fa-filters-col-' . self::COLUMN_WIDTH_WIDE];
		foreach ($this->columns as $key => $column) {
			$width = $this->getColumnWidth($key);
			foreach (['th', 'td'] as $tag) {
				$el = $column->getElementPrototype($tag);
				$current = $el->getAttribute('class');
				$current = is_array($current) ? $current : preg_split('~\s+~', (string) $current, -1, PREG_SPLIT_NO_EMPTY);
				// Html drží třídy jako seznam i jako [třída => true]
				$current = array_filter($current, static fn($class, $index): bool => !in_array(is_string($index) ? $index : $class, $classes, true), ARRAY_FILTER_USE_BOTH);
				if ($width !== self::COLUMN_WIDTH_AUTO) {
					$current['fa-filters-col-' . $width] = true;
				}
				$el->setAttribute('class', $current ?: null);
			}
		}
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
	 * Čipy filtrů s hodnotou v pořadí sloupců gridu, filtry bez sloupce na konci. Výchozí filtr ve výchozí
	 * hodnotě čip nemá.
	 *
	 * @param array<string, Filter> $panelFilters
	 * @return list<FilterChip>
	 */
	public function getFilterChips(array $panelFilters): array
	{
		$order = array_flip(array_keys($this->columns));
		$chips = [];
		foreach ($panelFilters as $key => $filter) {
			if (!$this->hasFilterValue($key) || $this->isDefaultFilterValue($key) || $this->isDefaultFilterAll($key)) {
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

	/** Typ pole v panelu: default (přepínač v liště), text, select, multiselect, bool (výběr Ano/Ne), date, daterange, range, checkbox. */
	public function getFilterFieldType(Filter $filter): string
	{
		return match (true) {
			$filter instanceof FilterSelect && $this->isDefaultFilterToggle($filter->getKey()) => 'default',
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
			$values = is_array($value) ? array_map('strval', $value) : [$value];
			// Celá vybraná skupina voleb (optgroup) je v čipu jednou položkou „Zásilkovna (vše)“
			foreach (is_array($value) ? $filter->getOptions() : [] as $group => $groupOptions) {
				$keys = is_array($groupOptions) ? array_map('strval', array_keys($groupOptions)) : [];
				if ($keys !== [] && count($keys) > 1 && array_diff($keys, $values) === []) {
					$labels[] = $translator->translate('fcadmin.datagrid.columnFilters.wholeGroup', ['group' => $this->getOptionLabel((string) $group)]);
					$values = array_diff($values, $keys);
				}
			}
			foreach ($values as $_value) {
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

	/**
	 * Aktivní je filtr s hodnotou, která není výchozí - výchozí filtr ve výchozí hodnotě je výchozí stav gridu.
	 * Na rozdíl od knihovny počítá i hodnotu „0“ (Aktivní: Ne).
	 */
	public function isFilterActive(?string $filter = null): bool
	{
		if ($filter !== null) {
			return parent::isFilterActive($filter);
		}

		foreach ($this->filter as $key => $value) {
			if ($key === self::FULLTEXT_FILTER) {
				continue;
			}
			if (self::isFilled($value) && !$this->isDefaultFilterValue((string) $key) && !$this->isDefaultFilterAll((string) $key)) {
				return true;
			}
		}

		return $this->forceFilterActive;
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
		parent::reload([...$snippets, 'columnFilters', 'columnFiltersActive', 'columnFiltersPresets', 'columnFiltersPresetList', 'table']);
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
