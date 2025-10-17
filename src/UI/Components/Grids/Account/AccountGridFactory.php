<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Account;

interface AccountGridFactory
{
	public function create(): AccountGrid;
}
