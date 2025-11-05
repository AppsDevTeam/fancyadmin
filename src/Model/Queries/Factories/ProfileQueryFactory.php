<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\ProfileQuery;

interface ProfileQueryFactory
{
	public function create(): ProfileQuery;
}