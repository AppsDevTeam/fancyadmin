<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface SsoQuery extends BaseQuery
{
	public function byName(string $name): static;
}
