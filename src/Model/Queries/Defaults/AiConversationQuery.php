<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\AiConversationQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\AiConversation>
 */
class AiConversationQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\AiConversationQuery
{
	use AiConversationQueryTrait;
}
