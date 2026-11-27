<?php

namespace ADT\FancyAdmin\UI\Components\Grids;

use ADT\Datagrid\Component\BaseGridDependencies;
use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Filter\FilterText;
use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use ADT\DoctrineComponents\EntityManager;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\Exporter\Model\Service\Exporter;
use ADT\Exporter\Model\Service\ExportRequest;
use ADT\Exporter\Model\Service\ExportSection;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\ContainerInject;
use ADT\FancyAdmin\DI\Injects\GridFilterFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\GridFilterQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\QueryObjectDataSourceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Grid\Export\GridExport;
use ADT\FancyAdmin\Model\Grid\Export\GridExportCsvGenerator;
use ADT\FancyAdmin\Model\Grid\Export\GridExportGenerator;
use ADT\FancyAdmin\Model\Grid\Export\GridExportXlsxGenerator;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use LogicException;
use Nette\Application\AbortException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Presenter;
use Nette\Security\User;

trait BaseGridTrait
{
	use SidePanel;
	use ControlTrait;
	use TranslatorInject;
	use SecurityUserInject;
	use BaseGridDependencies;
	use EntityManagerInject;
	use GridFilterQueryFactoryInject;
	use QueryObjectDataSourceInject;
	use GridFilterFormFactoryInject;
	use ContainerInject;

	public function __construct()
	{
		parent::__construct();

		// Stromový grid kreslíme vlastní šablonou (hlavička, sticky první sloupec a akce),
		// ke které patří styly v _datagridTree.scss.
		$this->monitor(Presenter::class, function (Presenter $presenter) {
			if ($this->getGrid()->isTreeView()) {
				$this->getGrid()->setTemplateFile(__DIR__ . '/DataGridTree.latte');
			}

			// Počet bez filtrů do „X z Y“ (ColumnFiltersTrait): stejný query objekt jako grid, jen bez filtrů gridu
			if (method_exists($this->getGrid(), 'setUnfilteredCountCallback')) {
				$this->getGrid()->setUnfilteredCountCallback(function (): int {
					$query = $this->createQueryObject();
					$this->initQueryObject($query);

					return $query->count();
				});
			}

			// Hledání ve viditelných sloupcích (ColumnFiltersTrait): každý textový filtr se pustí na vlastní query objekt
			// a grid se omezí na sjednocení nalezených ID - filtry s vlastní podmínkou jinak přes NEBO spojit nejde.
			$grid = $this->getGrid();
			if (method_exists($grid, 'canAddFulltext') && $grid->canAddFulltext()) {
				$grid->addFilterFulltext(function (QueryObjectInterface $query, string $value, array $filters): void {
					$ids = [];
					foreach ($filters as $filter) {
						$subQuery = $this->createQueryObject();
						$this->initQueryObject($subQuery);
						if ($condition = $filter->getConditionCallback()) {
							$condition($subQuery, $value);
						} else {
							// getCondition() knihovny čte hodnotu filtru, ta je nastavená jen u filtrů, které uživatel vyplnil
							assert($filter instanceof FilterText);
							$subQuery->by($filter->getColumns(), $value, QueryObjectByMode::CONTAINS);
						}
						foreach ($subQuery->fetchField('id') as $id) {
							$ids[$id] = $id;
						}
					}

					$query->by('id', $ids ? array_values($ids) : [0]);
				});
			}

			// Sestavy a nastavení sloupců (ColumnFiltersTrait), jen když je projekt zapnul v konfiguraci (datagrid)
			if (method_exists($grid, 'setPresetStorage')) {
				$key = $presenter->getName() . ':' . $grid->getFullName();
				if ($storage = $this->_container->getByType(GridColumnSettingsStorage::class, false)) {
					$grid->setColumnSettingsStorage($storage, $key);
				}
				if ($storage = $this->_container->getByType(GridPresetStorage::class, false)) {
					$grid->setPresetStorage($storage, $key);
				}
			}
		});
	}

	/**
	 * Panel Export (docs/datagrid-export.md): XLSX nebo CSV podle filtrů a hledání, se zobrazenými nebo všemi
	 * sloupci. Soubor vyrábí adt/exporter - ke stažení hned, na e-mail vždy na pozadí (stejně tak stažení nad
	 * `syncRowLimit`, to jde na e-mail přihlášeného). Volá se z `initGrid()`.
	 */
	protected function addExportPanel(DataGrid $grid, GridExport $export): void
	{
		if (!method_exists($grid, 'setExportPanel')) {
			throw new LogicException('Panel Export potřebuje grid s ColumnFiltersTrait.');
		}

		$exporter = $this->_container->getByType(Exporter::class);
		$ownEmail = $this->_securityUser->isLoggedIn() ? $this->getEmail() : null;

		$grid->setExportPanel(function (string $format, bool $visibleOnly, ?string $email) use ($grid, $export, $exporter, $ownEmail): void {
			if (!$visibleOnly && $export->getFullHeader() !== null) {
				$section = new ExportSection(GridExportGenerator::SECTION_FULL, $grid->getFilteredQueryObject(), []);
			} else {
				$columns = [];
				foreach (array_keys($visibleOnly ? $grid->getColumns() : $grid->getOrderedColumns()) as $key) {
					$columns[$key] = $grid->getColumnTitle((string) $key);
				}
				$section = new ExportSection(GridExportGenerator::SECTION_COLUMNS, $grid->getFilteredQueryObject(), $columns);
			}

			$record = $exporter->export(new ExportRequest(
				identifier: $export->getName(),
				sections: $section,
				generator: $this->_container->getByType($format === GridExportCsvGenerator::FORMAT ? GridExportCsvGenerator::class : GridExportXlsxGenerator::class),
				email: $email ?? $ownEmail,
				forceBackground: $email !== null,
			));

			$presenter = $this->getPresenter();
			if ($record->isInBackground()) {
				$presenter->flashMessageSuccess($this->getTranslator()->translate('fcadmin.datagrid.columnFilters.export.sent', ['email' => $record->getEmail()]));
				if (!$presenter->isAjax()) {
					$presenter->redirect('this');
				}

				return;
			}

			$presenter->sendResponse(new FileResponse((string) $exporter->getFilePath($record), (string) $record->getFileName()));
		}, $ownEmail);
	}

	public function getGridFilterQueryFactory(): GridFilterQueryFactory
	{
		return $this->_gridFilterQueryFactory;
	}

	public function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory
	{
		return $this->_queryObjectDataSource;
	}

	public function getForm(): BaseFormInterface
	{
		return $this->_gridFilterFormFactory->create()
			->setGrid($this);
	}

	public function getQueryObject(): QueryObjectInterface
	{
		return $this->_gridFilterQueryFactory->create();
	}

	public function getSecurityUser(): User
	{
		return $this->_securityUser;
	}

	public function getTranslator(): \Nette\Localization\ITranslator
	{
		return $this->_translator;
	}

	public function getEntityManager(): EntityManager
	{
		return $this->_em;
	}

	/**
	 * @throws AbortException
	 */
	public function redrawSidePanel(): never
	{
		$this->getPresenter()->payload->snippets[$this->getPresenter()->getSnippetId('sidePanel')] = $this['sidePanel']->renderToString();
		$this->getPresenter()->sendPayload();
	}

	public function handleEditAdvancedFilter(): void
	{
		$this->redrawSidePanel();
	}

	public function getEmail(): string
	{
		return $this->_securityUser->getIdentity()->getEmail();
	}
}
