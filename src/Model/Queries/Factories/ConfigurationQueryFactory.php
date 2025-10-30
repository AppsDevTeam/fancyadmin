<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\ConfigurationQuery;

interface ConfigurationQueryFactory
{
	public function create(): ConfigurationQuery;
}