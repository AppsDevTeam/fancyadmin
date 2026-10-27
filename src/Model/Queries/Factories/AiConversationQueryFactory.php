<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\AiConversationQuery;

interface AiConversationQueryFactory
{
	public function create(): AiConversationQuery;
}
