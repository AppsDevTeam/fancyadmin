<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\SsoQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Sso>
 */
class SsoQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\SsoQuery
{
	use SsoQueryTrait;
}
