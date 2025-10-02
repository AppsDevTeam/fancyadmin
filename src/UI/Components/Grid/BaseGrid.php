<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component\Grid;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\Datagrid\Component\DataGrid;
use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\DoctrineForms\BaseForm;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use App\Model\Doctrine\EntityManager;
use App\Model\Entities\GridFilter;
use App\Model\Filters;
use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use App\Model\Security\SecurityUser;
use App\UI\Portal\Components\Forms\GridFilter\GridFilterFormFactory;
use Contributte\Translation\Translator;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\AbortException;
use Nette\Security\User;

abstract class BaseGrid extends \ADT\Datagrid\Component\BaseGrid
{
	use SidePanel;

	abstract protected function initGrid(DataGrid $grid): void;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected IQueryObjectDataSourceFactory $queryObjectDataSource;

	#[Autowire]
	protected SecurityUser $securityUser;

	#[Autowire]
	protected EntityManager $em;

	#[Autowire]
	protected GridFilterQueryFactory $gridFilterQueryFactory;

	#[Autowire]
	protected GridFilterFormFactory $gridFilterFormFactory;

	#[Autowire]
	protected BackgroundQueue $backgroundQueue;

	#[Autowire]
	protected Filters $filters;

	protected ?GridFilter $entity = null;

	protected function getEntityManager(): EntityManager
	{
		return $this->em;
	}

	protected function getTranslator(): \Nette\Localization\Translator
	{
		return $this->translator;
	}

	protected function getGridFilterQueryFactory(): GridFilterQueryFactory
	{
		return $this->gridFilterQueryFactory;
	}

	protected function getSecurityUser(): User
	{
		return $this->securityUser;
	}

	protected function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory
	{
		return $this->queryObjectDataSource;
	}

	protected function getDataGridClass(): string
	{
		return DataGrid::class;
	}

	protected function getEntity(): callable|null|Entity
	{
		return $this->entity;
	}

	protected function getForm(): BaseForm
	{
		return $this->gridFilterFormFactory->create()
			->setGrid($this);
	}

	protected function getQueryObject(): QueryObject
	{
		return $this->gridFilterQueryFactory->create();
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
		return $this->securityUser->getIdentity()->getEmail();
	}
}
