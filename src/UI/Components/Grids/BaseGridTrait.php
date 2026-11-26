<?php

namespace ADT\FancyAdmin\UI\Components\Grids;

use ADT\Datagrid\Component\BaseGridDependencies;
use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use ADT\DoctrineComponents\EntityManager;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\ContainerInject;
use ADT\FancyAdmin\DI\Injects\GridFilterFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\GridFilterQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\QueryObjectDataSourceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Nette\Application\AbortException;
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
							$subQuery->by((fn(): array => $this->columns)->call($filter), $value, QueryObjectByMode::CONTAINS);
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
