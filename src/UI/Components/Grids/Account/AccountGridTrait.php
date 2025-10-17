<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Account;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;

trait AccountGridTrait
{
	use Editable;

	protected AclResourceNameEnum $aclResource = AclResourceNameEnum::BACKOFFICE_ACCOUNTS;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addFilterText('search', '', ['name']);

		$grid->addColumnText('name', 'fcadmin.grids.account.name');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AccountQueryFactory::class;
	}
}
