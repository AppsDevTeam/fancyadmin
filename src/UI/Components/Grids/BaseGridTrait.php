<?php

namespace ADT\FancyAdmin\UI\Components\Grids;

use ADT\Datagrid\Component\BaseGridDependencies;
use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\GridFilterFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\GridFilterQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\QueryObjectDataSourceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Nette\Application\AbortException;

trait BaseGridTrait
{
	use SidePanel;
	use TranslatorInject;
	use SecurityUserInject;
	use BaseGridDependencies;
	use GridFilterQueryFactoryInject;
	use QueryObjectDataSourceInject;
	use GridFilterFormFactoryInject;

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
