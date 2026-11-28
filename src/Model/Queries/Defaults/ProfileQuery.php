<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\ProfileQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Profile>
 */
class ProfileQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\ProfileQuery
{
	use ProfileQueryTrait;
}
