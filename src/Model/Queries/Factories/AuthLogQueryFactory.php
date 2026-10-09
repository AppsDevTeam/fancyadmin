<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\AuthLogQuery;

interface AuthLogQueryFactory
{
	public function create(): AuthLogQuery;
}
