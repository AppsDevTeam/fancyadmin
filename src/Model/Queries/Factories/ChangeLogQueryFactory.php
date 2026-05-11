<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\ChangeLogQuery;

interface ChangeLogQueryFactory
{
	public function create(): ChangeLogQuery;
}
