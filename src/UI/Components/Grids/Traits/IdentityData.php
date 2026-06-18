<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits;

use ADT\Datagrid\Component\DataGrid;

trait IdentityData
{
	use SearchFilter;

	protected function addIdentityData(DataGrid $grid, string $columnPrefix = ''): void
	{
		$this->addSearchFilter($grid, [$columnPrefix . 'firstName', $columnPrefix . 'lastName', $columnPrefix . 'email', $columnPrefix . 'phoneNumber']);
		$grid->addColumnText('fullName', 'fcadmin.grids.user.labels.fullName', $columnPrefix . 'fullName');
		$grid->addColumnText('email', 'fcadmin.grids.user.labels.email', $columnPrefix . 'email');
		$grid->addColumnText('phoneNumber', 'fcadmin.grids.user.labels.phoneNumber', $columnPrefix . 'phoneNumber');
	}
}
