<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\SsoQuery;

interface SsoQueryFactory
{
	public function create(): SsoQuery;
}
