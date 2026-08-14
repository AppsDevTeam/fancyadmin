<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface ApiKeyQuery extends BaseQuery
{
	public function byName(string $name): static;
	public function byRawKey(string $rawKey): static;
}
