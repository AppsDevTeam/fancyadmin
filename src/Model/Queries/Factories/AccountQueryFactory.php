<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\AccountQuery;

interface AccountQueryFactory
{
	public function create(): AccountQuery;
}