<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\OnetimeTokenQuery;

interface OnetimeTokenQueryFactory
{
	public function create(): OnetimeTokenQuery;
}