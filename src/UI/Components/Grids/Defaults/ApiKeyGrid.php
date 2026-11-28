<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Defaults;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGridTrait;

class ApiKeyGrid extends DefaultBaseGrid implements \ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGrid
{
	use ApiKeyGridTrait {
		initGrid as private initGridTrait;
	}

	public function initGrid(DataGrid $grid): void
	{
		parent::initGrid($grid);
		$this->initGridTrait($grid);
	}
}
