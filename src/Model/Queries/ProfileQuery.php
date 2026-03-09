<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\Filters\IsActiveFilter;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface ProfileQuery extends BaseQuery, IsActiveFilter
{
	public function byRole(int|array|AclRole $roles): static;
}