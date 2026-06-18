<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits;

use ADT\Datagrid\Component\DataGrid;

trait IdentityData
{
	protected function addIdentityData(DataGrid $grid, string $columnPrefix = '', array $searchFields = []): void
	{
		$grid->addFilterText('search', '', array_unique(array_merge([$columnPrefix . 'firstName', $columnPrefix . 'lastName', $columnPrefix . 'email', $columnPrefix . 'phoneNumber'], $searchFields)));
		$grid->addColumnText('fullName', 'fcadmin.grids.user.labels.fullName', $columnPrefix . 'fullName');
		$grid->addColumnText('email', 'fcadmin.grids.user.labels.email', $columnPrefix . 'email');
		$grid->addColumnText('phoneNumber', 'fcadmin.grids.user.labels.phoneNumber', $columnPrefix . 'phoneNumber');
	}
}
