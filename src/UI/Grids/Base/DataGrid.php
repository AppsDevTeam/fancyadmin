<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Grids\Base;

use ADT\DoctrineComponents\QueryObjectByMode;
use ADT\FancyAdmin\Model\Queries\Base\BaseQuery;
use ADT\FancyAdmin\Model\Queries\Interfaces\IGridFilterQueryFactory;
use ADT\Forms\BootstrapFormRenderer;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Json;
use ReflectionException;
use Ublaboo\DataGrid\Column\ColumnDateTime;
use Ublaboo\DataGrid\Column\ColumnNumber;
use Ublaboo\DataGrid\Export\ExportCsv;
use Ublaboo\DataGrid\Filter\FilterMultiSelect;
use Ublaboo\DataGrid\Utils\ArraysHelper;

/**
 * @method BaseGrid getParent()
 */
class DataGrid extends \Ublaboo\DataGrid\DataGrid
{

	const string SELECTED_GRID_FILTER_SESSION_KEY = 'selectedGridFilter';
	const string TEMPORARY_GRID_FILTER_SESSION_KEY = 'temporaryGridFilter';

	public const ACTION_NOT_DROPDOWN_ITEM = [
		'ajax datagrid-edit'
	];

	protected IGridFilterQueryFactory $gridFilterQueryFactory;

	public const TEMPLATE_DEFAULT = 'DataGrid.latte';

	public $strictSessionFilterValues = false;

	/** @var string */
	protected string $templateType;

	protected array $classes = [];
	protected array $htmlDataAttributes = [];

	protected bool $actionsToDropdown = true;

	protected bool $showTableFoot = true;

	public function __construct(
		string $templateType = self::TEMPLATE_DEFAULT,
		?Nette\ComponentModel\IContainer $parent = null,
		?string $name = null,
	) {
		$this->templateType = $templateType;
		parent::__construct($parent, $name);
	}

	public function getOriginalTemplateFile(): string
	{
		return __DIR__ . '/' . $this->templateType;
	}

	/**
	 * Get associative array of items_per_page_list
	 * @return array
	 */
	public function getItemsPerPageList(): array
	{
		$this->setItemsPerPageList([25], false);
		return parent::getItemsPerPageList();
	}

	public function createComponentFilter(): Form
	{
		$form = parent::createComponentFilter();
		$form->setRenderer(new BootstrapFormRenderer($form));
		return $form;
	}

	public function createComponentPaginator(): DataGridPaginator
	{
		$component = new DataGridPaginator(
			$this->getTranslator(),
			static::$iconPrefix,
		);
		$paginator = $component->getPaginator();

		$paginator->setPage($this->page);
		$paginator->setItemsPerPage($this->getPerPage());

		return $component;
	}

	public function render(): void
	{
		$gridClass = get_class($this->getParent());

		if ($this->getParameter(self::SELECTED_GRID_FILTER_SESSION_KEY)) {
			if (
				$selectedGridFilter = $this->gridFilterQueryFactory
					->create()
					->byId($this->getParameter(self::SELECTED_GRID_FILTER_SESSION_KEY))
					->fetchOneOrNull()
			) {
				$this->saveSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY, ['id' => $selectedGridFilter->getId(), 'name' => $selectedGridFilter->getName()]);
				$this->setFilter(['advancedSearch' => Json::encode($selectedGridFilter->getValue())]);
				$this->deleteSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY);
			}
		}

		if ($this->getSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY)) {
			$this->deleteSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY);
		}

		$this->template->actionsToDropdown = $this->actionsToDropdown;
		$this->template->translator = $this->translator;
		$this->template->gridClasses = $this->classes;
		$this->template->gridHtmlDataAttributes = $this->htmlDataAttributes;
		$this->template->showTableFoot = $this->showTableFoot;
		$this->template->toolbarButons = $this->toolbarButtons;
		$this->template->gridFilterColumns = Json::encode($this->getGridFilterFields());
		$this->template->gridClass = $gridClass;
		$this->template->temporaryGridFilter = $this->getSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY);
		$this->template->gridFilters = $this->gridFilterQueryFactory->create()->byGrid($gridClass)->fetch();

		parent::render();
	}

	public function addFilterMultiSelect(
		string $key,
		string $name,
		array $options,
		?string $column = null,
	): FilterMultiSelect {
		return parent::addFilterMultiSelect($key, $name, $options, $column)
			->setAttribute('class', []);
	}

	public function addColumnDateTime(string $key, string $name, ?string $column = null): ColumnDateTime
	{
		return parent::addColumnDateTime($key, $name, $column)->setAlign('left');
	}

	public function addColumnNumber(string $key, string $name, ?string $column = null): ColumnNumber
	{
		return parent::addColumnNumber($key, $name, $column)->setAlign('left');
	}

	public function addExportCsv(
		string $text,
		string $csvFileName,
		string $outputEncoding = 'utf-8',
		string $delimiter = ';',
		bool $includeBom = false,
		bool $filtered = true
	): ExportCsv {
		return parent::addExportCsv('', $csvFileName, $outputEncoding, $delimiter, $includeBom, $filtered)
			->setIcon('file-export');
	}

	public function isFilterActive(): bool
	{
		$filters = $this->filter;
		if (isset($filters['search'])) {
			$filters['search'] = null;
		}

		if (isset($filters['advancedSearch']) && $filters['advancedSearch'] === '[]') {
			$filters['advancedSearch'] = null;
		}

		$is_filter = ArraysHelper::testTruthy($filters);

		return $is_filter || $this->forceFilterActive;
	}

	public function handleResetFilter(): void
	{
		$searchFilter = $this->filter['search'] ?? null;
		$advancedSearchFilter = $this->filter['advancedSearch'] ?? null;
		parent::handleResetFilter();

		if ($searchFilter) {
			$this->filter['search'] = $searchFilter;
			$this->filter['advancedSearch'] = $advancedSearchFilter;
		}
	}

	public function handleResetGridFilter(): void
	{
		$this->deleteSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY);
		$this->deleteSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY);
		$this->handleResetFilter();
		$this->redirect('this');
	}

	/**
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function handleSortRows(): void
	{
		$itemId = $this->getParent()->getParameter('item_id');
		$nextId = $this->getParent()->getParameter('next_id');
		$previousId = $this->getParent()->getParameter('prev_id');
		$item = $this->getParent()->getQueryObject()->byId($itemId)->fetchOne();

		if ($previousId) {
			$previousItem = $this->getParent()->getQueryObject()->byId($previousId)->fetchOne();
			$newPosition = $previousItem->getPosition() + 1;
		} else if ($nextId) {
			$nextItem = $this->getParent()->getQueryObject()->byId($nextId)->fetchOne();
			$newPosition = $nextItem->getPosition() - 1;
		} else {
			$newPosition = 0;
		}

		if ($newPosition < 0) {
			$newPosition = 0;
		}

		$item->setPosition($newPosition);
		$this->getParent()->getEntityManager()->flush();
	}

	public function addClasses(array|string $classes): void
	{
		if (!is_array($classes)) {
			$this->classes[] = $classes;
		} else {
			$this->classes = array_merge($this->classes, $classes);
		}
	}

	public function getGridFilterFields(array $fields = [], bool $includeAllColumns = true): array
	{
		if ($includeAllColumns) {
			$fields = [
				...array_map(fn(string $key) => ['id' => $key], array_keys($this->columns)),
				...$fields
			];
		}

		foreach ($fields as &$field) {
			$id = $field['id'];
			$column = $this->columns[$id];

			if (!empty($column)) {
				if (empty($field['type'])) {
					$field['type'] = 'text';

					if (!empty($this->filters[$id]) && $this->filters[$id] instanceof FilteBarSelect) {
						$field['type'] = 'list';
						$options = $this->filters[$id]->getOptions();
						$field['list'] = array_map(function ($value, $key) {
							return ['id' => $key, 'label' => $value];
						}, $options, array_keys($options));
					}

					if ($column instanceof ColumnDateTime) {
						$field['type'] = 'date';
					}
				}

				$field['label'] = $this->translator->translate($column->getName());
			}
		}

		return $fields;
	}

	public function addAdvancedFilteredSearch(array $fields = [], bool $includeAllColumns = true): void
	{
		$fields = $this->getGridFilterFields($fields, $includeAllColumns);

		$this->addFilterText('advancedSearch', '', [])
			->setCondition(function (BaseQuery $query, $value) {
				if ($value) {
					$advanceSearch = Json::decode($value, Json::FORCE_ARRAY);

					$seenValues = [];
					foreach ($advanceSearch as $key => $item) {
						$fieldValue = $item['value'];

						if (in_array($fieldValue, $seenValues)) {
							// Odstraníme duplicity
							unset($advanceSearch[$key]);
						} else {
							$seenValues[] = $fieldValue;
						}
					}
					$advanceSearch = array_values($advanceSearch);

					foreach ($advanceSearch as $searchFilter) {
						$operatorMap = [
							'eq' => QueryObjectByMode::STRICT,
							'ne' => QueryObjectByMode::NOT_EQUAL,
							'sw' => QueryObjectByMode::STARTS_WITH,
							'ct' => QueryObjectByMode::CONTAINS,
							'nct' => QueryObjectByMode::NOT_CONTAINS,
							'fw' => QueryObjectByMode::ENDS_WITH,
							'in' => QueryObjectByMode::IN_ARRAY,
							'null' => QueryObjectByMode::IS_EMPTY,
							'nn' => QueryObjectByMode::IS_NOT_EMPTY,
							'gt' => QueryObjectByMode::GREATER_OR_EQUAL,
							'lt' => QueryObjectByMode::LESS_OR_EQUAL,
							'bw' => QueryObjectByMode::BETWEEN,
							'nbw' => QueryObjectByMode::NOT_BETWEEN,
						];

						if (!empty($searchFilter['value2'])) {
							$value = [
								Utils::getDateTimeFromArray($searchFilter['value']) ?: $searchFilter['value'],
								Utils::getDateTimeFromArray($searchFilter['value2']) ?: $searchFilter['value2'],
							];
						} else {
							$value = Utils::getDateTimeFromArray($searchFilter['value']) ?: $searchFilter['value'];
							if ($operatorMap[$searchFilter['operator']] === QueryObjectByMode::IN_ARRAY && !is_array($value)) {
								$delimiter = $searchFilter['delimiter'] ?? ',';
								$value = explode($delimiter, $value);
							}
						}

						if (
							Utils::realEmpty($value)
							&& !in_array($operatorMap[$searchFilter['operator']], [QueryObjectByMode::IS_EMPTY, QueryObjectByMode::IS_NOT_EMPTY])
						) {
							continue;
						}

						$label = $searchFilter['label'];

						try {
							$column = array_keys($this->getFilter($label)->getCondition());
						} catch (\Exception) {
							$column = $this->getColumn($label)->getColumn();
						}

						$query->by(
							(!empty($column) ? $column : $label),
							$value,
							$operatorMap[$searchFilter['operator']] ?? QueryObjectByMode::STRICT
						);
					}
				}
			})
			->setAttribute('data-filter-fields', Json::encode($fields));
	}

	public function setGridFilterQuery(IGridFilterQueryFactory $queryFactory): self
	{
		$this->gridFilterQueryFactory = $queryFactory;
		return $this;
	}
}
