<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface ProfileQuery extends BaseQuery
{
	public function byRole(int|array|AclRole $roles): static;
}