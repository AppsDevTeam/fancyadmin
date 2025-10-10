<?php

namespace ADT\FancyAdmin\UI\Components\Grids\AclRole;

interface AclRoleGridFactory
{
	public function create(): AclRoleGrid;
}