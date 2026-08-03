<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\PasskeyQuery;

interface PasskeyQueryFactory
{
	public function create(): PasskeyQuery;
}
