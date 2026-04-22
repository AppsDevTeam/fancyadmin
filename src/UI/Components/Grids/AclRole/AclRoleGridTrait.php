<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\AclRole;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use Contributte\Datagrid\Exception\DatagridException;

trait AclRoleGridTrait
{
	use Editable;

	/**
	 * @throws DatagridException
	 */
	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('name', 'fcadmin.grids.aclRole.name');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AclRoleQueryFactory::class;
	}
}
