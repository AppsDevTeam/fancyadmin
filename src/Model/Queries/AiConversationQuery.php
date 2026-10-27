<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface AiConversationQuery extends BaseQuery
{
	public function byIdentity(int|Identity $identity): static;
	public function notDeleted(): static;
}
