<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

interface GridFilterQueryFactory
{
	public function create(): \ADT\FancyAdmin\Model\Queries\GridFilterQuery;
}
