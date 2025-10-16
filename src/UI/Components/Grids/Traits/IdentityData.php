<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits;

use ADT\Datagrid\Component\DataGrid;

trait IdentityData
{
	protected function addIdentityData(DataGrid $grid, string $columnPrefix = ''): void
	{
		$grid->addColumnText('fullName', 'app.grids.user.labels.fullName', $columnPrefix . 'fullName');
		$grid->addColumnText('username', 'app.grids.user.labels.emailUsername', $columnPrefix . 'username');
		$grid->addColumnText('phoneNumber', 'app.grids.user.labels.phoneNumber', $columnPrefix . 'phoneNumber');
	}
}
