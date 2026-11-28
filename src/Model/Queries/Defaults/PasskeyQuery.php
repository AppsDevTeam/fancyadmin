<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\PasskeyQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Passkey>
 */
class PasskeyQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\PasskeyQuery
{
	use PasskeyQueryTrait;
}
