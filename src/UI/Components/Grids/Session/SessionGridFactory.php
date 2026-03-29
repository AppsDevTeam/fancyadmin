<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Session;

interface SessionGridFactory
{
	public function create(): SessionGrid;
}
