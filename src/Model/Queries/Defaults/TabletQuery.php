<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\TabletQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Tablet>
 */
class TabletQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\TabletQuery
{
	use TabletQueryTrait;
}
