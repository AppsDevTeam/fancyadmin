<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface AccountQuery extends BaseQuery
{
	public function byIdOrParentId(string|Entity $idOrParentId): static;
}