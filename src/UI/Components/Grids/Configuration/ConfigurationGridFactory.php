<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Configuration;

interface ConfigurationGridFactory
{
	public function create(): ConfigurationGrid;
}
