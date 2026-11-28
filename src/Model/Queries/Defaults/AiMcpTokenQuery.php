<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\AiMcpTokenQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\AiMcpToken>
 */
class AiMcpTokenQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\AiMcpTokenQuery
{
	use AiMcpTokenQueryTrait;
}
