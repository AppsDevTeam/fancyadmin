<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\ApiKeyQuery;

interface ApiKeyQueryFactory
{
	public function create(): ApiKeyQuery;
}
