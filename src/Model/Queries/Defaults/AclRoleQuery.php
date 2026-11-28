<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\AclRoleQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\AclRole>
 */
class AclRoleQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\AclRoleQuery
{
	use AclRoleQueryTrait;
}
