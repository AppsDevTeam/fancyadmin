<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits;

use ADT\Datagrid\Component\DataGrid;

trait IdentityData
{
	protected function addIdentityData(DataGrid $grid, string $columnPrefix = ''): void
	{
		$grid->addFilterText('search', '', [$columnPrefix . 'firstName', $columnPrefix . 'lastName', $columnPrefix . 'email', $columnPrefix . 'phoneNumber']);
		$grid->addColumnText('fullName', 'fcadmin.grids.user.labels.fullName', $columnPrefix . 'fullName');
		$grid->addColumnText('email', 'fcadmin.grids.user.labels.email', $columnPrefix . 'email');
		$grid->addColumnText('phoneNumber', 'fcadmin.grids.user.labels.phoneNumber', $columnPrefix . 'phoneNumber');
	}
}
