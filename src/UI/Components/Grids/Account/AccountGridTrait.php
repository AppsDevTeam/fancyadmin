<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Account;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;

trait AccountGridTrait
{
	use Editable;
	use FancyAdminInject;
	use SearchFilter;

	public function initGrid(DataGrid $grid): void
	{
		$this->addSearchFilter($grid, ['name']);

		$grid->addColumnText('name', 'fcadmin.grids.account.name');

		$grid->addAction('enter', 'Vstoupit do účtu')
			->setIcon('sign-in-alt')
			->setClass('btn btn-primary btn-sm');
	}

	public function handleEnter(int $id): void
	{
		$this->presenter->redirect($this->_fancyAdmin->getDefaultCustomerRoute(), ['selectedAccount' => $id]);
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AccountQueryFactory::class;
	}
}
