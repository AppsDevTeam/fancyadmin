<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Defaults;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\Profile\ProfileGridTrait;

class ProfileGrid extends DefaultBaseGrid implements \ADT\FancyAdmin\UI\Components\Grids\Profile\ProfileGrid
{
	use ProfileGridTrait {
		initGrid as private initGridTrait;
	}

	public function initGrid(DataGrid $grid): void
	{
		parent::initGrid($grid);
		$this->initGridTrait($grid);
	}
}
