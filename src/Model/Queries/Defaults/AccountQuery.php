<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\AccountQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Account>
 */
class AccountQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\AccountQuery
{
	use AccountQueryTrait;
}
