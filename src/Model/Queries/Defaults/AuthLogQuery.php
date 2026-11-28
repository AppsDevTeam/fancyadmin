<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\FancyAdmin\Model\Queries\AuthLogQueryTrait;

/**
 * Entitu určuje trait (log balíčku nebo adt/doctrine-loggable), DI ji nenastavuje.
 *
 * @extends QueryObject<object>
 */
class AuthLogQuery extends QueryObject implements \ADT\FancyAdmin\Model\Queries\AuthLogQuery
{
	use AuthLogQueryTrait;

	protected function setDefaultOrder(): void
	{
	}
}
