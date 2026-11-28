<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Defaults;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\AclRole\AclRoleGridTrait;

class AclRoleGrid extends DefaultBaseGrid implements \ADT\FancyAdmin\UI\Components\Grids\AclRole\AclRoleGrid
{
	use AclRoleGridTrait {
		initGrid as private initGridTrait;
	}

	public function initGrid(DataGrid $grid): void
	{
		parent::initGrid($grid);
		$this->initGridTrait($grid);
	}
}
