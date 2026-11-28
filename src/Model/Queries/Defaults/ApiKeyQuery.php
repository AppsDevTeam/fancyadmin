<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\ApiKeyQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\ApiKey>
 */
class ApiKeyQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\ApiKeyQuery
{
	use ApiKeyQueryTrait;
}
