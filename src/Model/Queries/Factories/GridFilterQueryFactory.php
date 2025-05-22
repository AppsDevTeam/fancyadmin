<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\Interfaces\IGridFilterQueryFactory;

interface GridFilterQueryFactory
{
	public function create(): IGridFilterQueryFactory;
}
