<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\FancyAdmin\Model\Queries\ConfigurationQueryTrait;

/**
 * @extends DefaultBaseQuery<\ADT\FancyAdmin\Model\Entities\Configuration>
 */
class ConfigurationQuery extends DefaultBaseQuery implements \ADT\FancyAdmin\Model\Queries\ConfigurationQuery
{
	use ConfigurationQueryTrait;

	protected function setDefaultOrder(): void
	{
		$this->orderBy('key');
	}
}
