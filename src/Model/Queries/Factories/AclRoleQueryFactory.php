<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\AclRoleQuery;

interface AclRoleQueryFactory
{
	public function create(): AclRoleQuery;
}