<?php

namespace ADT\FancyAdmin\UI\Components\Grids;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\Datagrid\Component\DataGrid;
use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\DoctrineForms\BaseForm;
use ADT\FancyAdmin\DI\Injects\BackgroundQueryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FiltersInject;
use ADT\FancyAdmin\DI\Injects\GridFilterFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\GridFilterQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\QueryObjectDataSourceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use App\Model\Doctrine\EntityManager;
use App\Model\Entities\GridFilter;
use App\Model\Filters;
use App\Model\Queries\Factories\GridFilterQueryFactory;
use App\Model\Security\SecurityUser;
use App\UI\Portal\Components\Forms\GridFilter\GridFilterFormFactory;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\AbortException;
use Nette\Localization\Translator;
use Nette\Security\User;

trait BaseGridTrait
{
	use SidePanel;
	use FiltersInject;
	use TranslatorInject;
	use SecurityUserInject;
	use EntityManagerInject;
	use BackgroundQueryInject;
	use QueryObjectDataSourceInject;
	use GridFilterFormFactoryInject;
	use GridFilterQueryFactoryInject;

	public ?GridFilter $entity = null;

	public function getEntityManager(): EntityManager
	{
		return $this->_em;
	}

	public function getTranslator(): Translator
	{
		return $this->_translator;
	}

	public function getGridFilterQueryFactory(): GridFilterQueryFactory
	{
		return $this->_gridFilterQueryFactory;
	}

	public function getSecurityUser(): User
	{
		return $this->_securityUser;
	}

	public function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory
	{
		return $this->_queryObjectDataSource;
	}

	public function getDataGridClass(): string
	{
		return DataGrid::class;
	}

	public function getEntity(): callable|null|Entity
	{
		return $this->entity;
	}

	public function getForm(): BaseForm
	{
		return $this->_gridFilterFormFactory->create()
			->setGrid($this);
	}

	public function getQueryObject(): QueryObject
	{
		return $this->_gridFilterQueryFactory->create();
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
