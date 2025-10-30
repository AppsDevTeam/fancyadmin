<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits;

use ADT\Datagrid\Component\DataGrid;

trait IdentityData
{
	protected function addIdentityData(DataGrid $grid, string $columnPrefix = ''): void
	{
		$grid->addColumnText('fullName', 'fcadmin.grids.user.labels.fullName', $columnPrefix . 'fullName');
		$grid->addColumnText('username', 'fcadmin.grids.user.labels.emailUsername', $columnPrefix . 'username');
		$grid->addColumnText('phoneNumber', 'fcadmin.grids.user.labels.phoneNumber', $columnPrefix . 'phoneNumber');
	}
}
