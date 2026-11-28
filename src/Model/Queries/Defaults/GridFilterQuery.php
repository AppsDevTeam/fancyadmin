<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\GridFilterQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\GridFilter>
 */
class GridFilterQuery extends DefaultBaseQuery implements \ADT\Datagrid\Model\Queries\GridFilterQuery
{
	use GridFilterQueryTrait;
}
