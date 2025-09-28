<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\IdentityQuery;

interface IdentityQueryFactory
{
	public function create(): IdentityQuery;
}