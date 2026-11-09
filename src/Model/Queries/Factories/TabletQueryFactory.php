<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\TabletQuery;

interface TabletQueryFactory
{
	public function create(): TabletQuery;
}
