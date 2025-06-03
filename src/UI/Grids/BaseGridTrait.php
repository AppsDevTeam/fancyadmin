<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Grids;

use ADT\DoctrineComponents\EntityManager;
use ADT\DoctrineComponents\QueryObject;
use ADT\FancyAdmin\Model\Queries\Filters\IsActiveFilter;
use ADT\FancyAdmin\Model\Security\SecurityUserInterface;
use ADT\FancyAdmin\UI\Presenters\BasePresenter;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Closure;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Localization\Translator;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;
use Ublaboo\DataGrid\Exception\DataGridException;

/**
 * @property-read DataGrid $grid
 * @method BasePresenter getPresenter()
 */
trait BaseGridTrait
{
	abstract protected function initGrid(DataGrid $grid): void;
	abstract protected function getTranslator(): Translator;
	abstract protected function getGridFilterQueryFactory();
	abstract protected function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory;
	abstract protected function getSecurityUser(): SecurityUserInterface;
	abstract protected function createQueryObject(): QueryObject;
	abstract protected function getEntityManager(): EntityManager;

	/** @var callable */
	protected $onDelete;
	protected static string $templateFile = DataGrid::TEMPLATE_DEFAULT;
	protected bool $withoutIsActiveColumn = false;
	private ?QueryObject $queryObject = null;

	/**
	 * @throws DataGridException
	 */
	final protected function createComponentGrid(): DataGrid
	{
		$grid = new DataGrid(static::$templateFile);
		$grid->setTranslator($this->getTranslator());
		$grid->setGridFilterQuery($this->getGridFilterQueryFactory());

		$grid->setOuterFilterRendering();

		$queryObject = $this->createQueryObject();

		$this->initQueryObject($queryObject);

		$queryObjectDataSource = $this->getQueryObjectDataSourceFactory()->create($queryObject);

		if ($this->getDataSourceFilterCallback()) {
			$queryObjectDataSource->setFilterCallback($this->getDataSourceFilterCallback());
		}
		$grid->setDataSource($queryObjectDataSource);

		if ($this->allowEdit()) {
			$grid->addAction('edit', '')
				->setIcon('edit')
				->setClass('ajax datagrid-edit');
		}

		if ($this->allowDelete() && $this->getSecurityUser()->isAllowed($this->allowDelete()->getAcl())) {
			$grid->addAction('delete', 'Smazat', 'delete!')
				->setIcon('trash')
				->setClass('ajax datagrid-delete')
				->setConfirmation(new StringConfirmation($this->getTranslator()->translate('action.delete.confirm')));
		}
		$this->initGrid($grid);
		$this->addIsActive($grid);

		if ($grid->isSortable()) {
			$grid->setSortableHandler('sortRows!');
		}

		if ($grid->getTemplateFile() === $grid->getOriginalTemplateFile()) {
			$_reflectionClass = new ReflectionClass($this);
			$grid->setTemplateFile(dirname($_reflectionClass->getFileName()) . '/' . $_reflectionClass->getShortName() . '.latte');
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

	protected function initQueryObject($queryObject): void
	{
		if ($queryObject instanceof IsActiveFilter) {
			$queryObject->disableIsActiveFilter();
		}
	}

	public function render(): void
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
			} catch (InvalidLinkException | TypeError) {
				$this->getPresenter()->{$methodName}($this->getQueryObject()->byId($id)->fetchOne());
			}
		} else {
			// because of "Argument $order passed to App\Modules\SystemModule\Orders\OrdersPresenter::actionEdit() must be App\Model\Entity\Order, integer given."
			// method Presenter::argsToParams doesn't respect router
			try {
				$this->getPresenter()->redirect($this->allowEdit()->redirect, $id);
			} catch (InvalidLinkException) {
				$this->getPresenter()->redirect($this->allowEdit()->redirect, $this->getQueryObject()->byId($id)->fetchOne());
			}
		}
	}

	/**
	 * @throws BadRequestException
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 * @throws Exception
	 */
	final public function handleDelete($id): void
	{
		if (!$this->allowDelete()) {
			$this->getPresenter()->error();
		}

		if (!$this->getSecurityUser()->isAllowed($this->allowDelete()->acl)) {
			$this->getPresenter()->error();
		}

		if (!$entity = $this->getQueryObject()->byId($id)->fetchOneOrNull()) {
			$this->getPresenter()->error();
		}

		if ($this->allowDelete()->onDelete && !($this->allowDelete()->onDelete)($entity)) {
			return;
		}

		if ($this->getEntityManager()->isPossibleToDeleteEntity($entity)) {
			$this->getEntityManager()->remove($entity);
			$this->getEntityManager()->flush();
			$this->getPresenter()->flashMessageSuccess('action.delete.yes');
			$this->grid->redrawControl();
		} else {
			$this->getPresenter()->flashMessageError('app.grids.flashes.cantDelete');
			$this->getPresenter()->redrawControl('flashes');
		}
	}

	final public function addFilterQuery(DataGrid $grid): void
	{
		$grid->addFilterText('q', '')
			->setTemplate('datagrid_filter_q.latte')
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

	protected function getDataSource(): QueryObject
	{
		if ($this->queryObject) {
			return $this->queryObject;
		}

		return $this->createQueryObject();
	}

	/**
	 * @throws DataGridException
	 */
	protected function addIsActive(DataGrid $grid): void
	{
		$class = $this->createQueryObject()->getEntityClass();
		$metadata = $this->getEntityManager()->getClassMetadata($class);

		if (
			$this->withoutIsActiveColumn
			|| isset($grid->getColumns()['isActive'])
			|| !$metadata->hasField('isActive')
		) {
			return;
		}

		$i = 1;
		$order = [];

		foreach ($grid->getColumns() as $key => $column) {
			if ($i === 2) {
				$order[] = 'isActive';
			}

			$order[] = $key;
			$i++;
		}

		$grid->addColumnText('isActive', 'app.forms.global.isActive');
		$grid->setColumnsOrder($order);
	}

	protected function getQueryObject(): QueryObject
	{
		if ($this->queryObject) {
			return $this->queryObject;
		}

		return $this->queryObject = $this->createQueryObject();
	}
}
