<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\IdentityQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Identity>
 */
class IdentityQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\IdentityQuery, \ADT\DoctrineAuthenticator\OTP\IdentityQuery
{
	use IdentityQueryTrait;
}
