<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Abstract;

use ADT\Components\AjaxSelect\Interfaces\OrByIdFilterInterface;
use ADT\DoctrineComponents\QueryObject\QueryObject;

/**
 * @extends QueryObject<TEntity>
 * @template TEntity of object
 */
abstract class BaseQuery extends QueryObject implements OrByIdFilterInterface, Factories\BaseQuery
{
	use BaseQueryTrait;
}
