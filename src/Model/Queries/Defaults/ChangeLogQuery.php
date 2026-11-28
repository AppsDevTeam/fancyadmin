<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\FancyAdmin\Model\Queries\ChangeLogQueryTrait;

/**
 * Entitu určuje trait (log balíčku nebo adt/doctrine-loggable), DI ji nenastavuje.
 *
 * @extends QueryObject<object>
 */
class ChangeLogQuery extends QueryObject implements \ADT\FancyAdmin\Model\Queries\ChangeLogQuery
{
	use ChangeLogQueryTrait;

	protected function setDefaultOrder(): void
	{
	}
}
