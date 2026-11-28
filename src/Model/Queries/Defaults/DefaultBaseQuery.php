<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Defaults;

use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQueryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Základ výchozích query balíčku, které DI rozšíření zaregistruje, když projekt vlastní query nemá.
 * Třídu entity dostane z rozšíření (entita projektu implementující rozhraní balíčku) - odvození
 * z názvu třídy (BaseQueryTrait) by vedlo do namespace balíčku, kde entity nejsou.
 *
 * @template TEntity of object
 * @extends QueryObject<TEntity>
 */
abstract class DefaultBaseQuery extends QueryObject implements BaseQuery
{
	use BaseQueryTrait;

	/**
	 * Třída entity v konstruktoru, ne setterem: QueryObject volá init() už v konstruktoru
	 * a init() některých traitů (GridFilterQueryTrait) entitu potřebuje.
	 *
	 * @param class-string<TEntity> $entityClass
	 */
	public function __construct(EntityManagerInterface $em, private readonly string $entityClass)
	{
		parent::__construct($em);
	}

	/**
	 * @return class-string<TEntity>
	 */
	public function getEntityClass(): string
	{
		return $this->entityClass;
	}

	protected function applySecurityFilter(): void
	{
	}

	protected function applyAccountFilter(QueryBuilder $qb, Account $account): void
	{
	}

	protected function setDefaultOrder(): void
	{
	}
}
