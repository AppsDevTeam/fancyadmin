<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface AiMcpTokenQuery extends BaseQuery
{
	public function byIdentity(int|Identity $identity): static;
	public function byRawToken(string $rawToken): static;
}
