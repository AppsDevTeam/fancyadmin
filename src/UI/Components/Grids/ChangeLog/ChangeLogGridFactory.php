<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\ChangeLog;

interface ChangeLogGridFactory
{
	public function create(): ChangeLogGrid;
}
