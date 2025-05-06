<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Grid\Base;

use ADT\DoctrineComponents\QueryObject;
use ADT\FancyAdmin\Presenters\BasePresenter;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\latte\Filters;
use ADT\FancyAdmin\Model\Queries\Base\BaseQuery;
use ADT\FancyAdmin\Model\Queries\Factories\GridFilterQueryFactory;
use ADT\FancyAdmin\Model\Queries\Filters\IsActiveInterface;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\FancyAdmin\Model\Services\DeleteService;
use Closure;
use Contributte\Translation\Translator;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\DI\Container;
use ReflectionException;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;
use Ublaboo\DataGrid\Exception\DataGridException;

/**
 * @property-read DataGrid $grid
 * @property-read BasePresenter $presenter
 */
abstract class BaseGrid extends Control
{
	use AutowireProperties;
	use AutowireComponentFactories;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected IQueryObjectDataSourceFactory $queryObjectDataSource;

	#[Autowire]
	protected SecurityUser $securityUser;

	#[Autowire]
	protected EntityManager $em;

	#[Autowire]
	protected DeleteService $deleteService;

	#[Autowire]
	protected GridFilterQueryFactory $gridFilterQueryFactory;

	#[Autowire]
	protected Filters $filters;

	/** @var callable */
	protected $onDelete;

	protected static string $templateFile = DataGrid::TEMPLATE_DEFAULT;

	abstract protected function getQueryObjectFactoryClass(): string;
	abstract protected function initGrid(DataGrid $grid): void;

	final protected function createComponentGrid(): DataGrid
	{
		$grid = new DataGrid(static::$templateFile);
		$grid->setTranslator($this->translator);
		$grid->setGridFilterQuery($this->gridFilterQueryFactory);

		$grid->setOuterFilterRendering();

		$queryObject = $this->createQueryObject();

		if (method_exists($this, 'initDataSource')) {
			$this->initDataSource($queryObject);
		}

		$queryObjectDataSource = $this->queryObjectDataSource->create($queryObject);

		if ($this->getDataSourceFilterCallback()) {
			$queryObjectDataSource->setFilterCallback($this->getDataSourceFilterCallback());
		}
		$grid->setDataSource($queryObjectDataSource);

		if ($this->allowEdit()) {
			$grid->addAction('edit', '', 'edit!')
				->setIcon('edit')
				->setClass('ajax datagrid-edit');
		}

		if ($this->allowDelete() && $this->securityUser->isAllowed($this->allowDelete()->getAcl())) {
			$grid->addAction('delete', 'Smazat', 'delete!')
				->setIcon('trash')
				->setClass('ajax datagrid-delete')
				->setConfirmation(new StringConfirmation('action.delete.confirm'));
		}
		$this->initGrid($grid);

		if ($grid->isSortable()) {
			$grid->setSortableHandler('sortRows!');
		}

		if ($grid->getTemplateFile() === $grid->getOriginalTemplateFile()) {
			$_reflectionClass = new \ReflectionClass($this);
			$grid->setTemplateFile(dirname($_reflectionClass->getFileName()) . 'BaseGrid.php/' . $_reflectionClass->getShortName() . '.Latte');
		}

		return $grid;
	}

	public function getYesNoOptions(): array
	{
		return [
			'0' => 'no',
			'1' => 'yes'
		];
	}

	public function getGrid(): DataGrid
	{
		return $this['grid'];
	}

	protected function createQueryObject(): BaseQuery
	{
		/** @var BaseQuery $queryObject */
		$queryObject = $this->getDic()->getByType($this->getQueryObjectFactoryClass())->create();

		if ($queryObject instanceof IsActiveInterface) {
			$queryObject->disableIsActiveFilter();
		}

		return $queryObject;
	}

	private function createBaseEntityQueryObject(): BaseQuery
	{
		if (method_exists($this, 'getBaseEntityQueryFactoryClass')) {
			$queryObjectFactory = $this->getDic()->getByType($this->getBaseEntityQueryFactoryClass());
			return $queryObjectFactory->create();
		}
		return $this->createQueryObject();
	}

	/**
	 * @throws DataGridException
	 */
	final public function render(): void
	{
		$this->template->setFile(__DIR__ . '/BaseGrid.latte')->render();
	}

	protected function getDataSourceFilterCallback(): ?Closure
	{
		return null;
	}

	/**
	 * @throws AbortException
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 * @throws NoResultException|InvalidLinkException
	 */
	final public function handleEdit(int $id): void
	{
		if (str_ends_with($this->allowEdit()->redirect, '!')) {
			$methodName = rtrim('handle' . ucfirst($this->allowEdit()->redirect), '!');

			try {
				$this->getPresenter()->{$methodName}($id);
			} catch (InvalidLinkException | \TypeError) {
				$this->getPresenter()->{$methodName}($this->createBaseEntityQueryObject()->byId($id)->fetchOne());
			}
		} else {
			// because of "Argument $order passed to App\Modules\SystemModule\Orders\OrdersPresenter::actionEdit() must be ADT\FancyAdmin\Model\Entities\Order, integer given."
			// method Presenters::argsToParams doesn't respect router
			try {
				$this->presenter->redirect($this->allowEdit()->redirect, $id);
			} catch (InvalidLinkException) {
				$this->presenter->redirect($this->allowEdit()->redirect, $this->createBaseEntityQueryObject()->byId($id)->fetchOne());
			}
		}
	}

	/**
	 * @throws BadRequestException
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 */
	final public function handleDelete($id): void
	{
		if (!$this->allowDelete()) {
			$this->error();
		}

		if (!$this->securityUser->isAllowed($this->allowDelete()->acl)) {
			$this->error();
		}

		if (!$entity = $this->createBaseEntityQueryObject()->byId($id)->fetchOneOrNull()) {
			$this->error();
		}

		if ($this->allowDelete()->onDelete && !($this->allowDelete()->onDelete)($entity)) {
			return;
		}

		if ($this->deleteService->isPossibleToDeleteEntity($entity)) {
			$this->deleteService->delete($entity);
			$this->presenter->flashMessageSuccess('action.delete.yes');
			$this->grid->redrawControl();
		} else {
			$this->presenter->flashMessageError('app.grids.flashes.cantDelete');
			$this->presenter->redrawControl('flashes');
		}
	}

	final public function addFilterQuery(DataGrid $grid): void
	{
		$grid->addFilterText('q', '')
			->setTemplate('datagrid_filter_q.Latte')
			->setCondition(function ($query, $value) {
				$query->byQuery($value);
			});
	}

	protected function allowEdit(): ?EditParams
	{
		return null;
	}

	protected function allowDelete(): ?DeleteParams
	{
		return null;
	}

	protected function getDic(): Container
	{
		return $this->autowirePropertiesLocator;
	}

	public function getEntityManager(): EntityManager
	{
		return $this->em;
	}

	public function getQueryObject(): QueryObject
	{
		return $this->createQueryObject();
	}
}
