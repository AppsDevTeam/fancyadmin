<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures\Queries;

use ADT\DoctrineComponents\QueryObject\Filters\IsActiveFilter;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQueryTrait;
use Doctrine\ORM\QueryBuilder;

/**
 * Query object, ktery misto do databaze zapisuje volani do pole.
 *
 * Nahrazuje predka z adt/doctrine-components - BaseQueryTrait na nej vola parent::init()
 * a parent::byId(), ale krome toho pracuje jen s vlastnim polem filtru.
 */
abstract class QueryObjectStub
{
	public array $filter = [];

	/** @var list<array> */
	public array $byCalls = [];

	public bool $initCalled = false;

	public bool $isActiveFilterDisabled = false;

	public function init(): void
	{
		$this->initCalled = true;
	}

	public function by(array|string $column, mixed $value = null, QueryObjectByMode $mode = QueryObjectByMode::AUTO): static
	{
		$this->byCalls[] = ['column' => $column, 'value' => $value, 'mode' => $mode];
		return $this;
	}

	public function byId($id): static
	{
		$this->byCalls[] = ['column' => 'id', 'value' => $id, 'mode' => QueryObjectByMode::AUTO];
		return $this;
	}

	public function orById($id): static
	{
		return $this;
	}

	public function orderBy(array|string $field, ?string $order = null): static
	{
		return $this;
	}

	public function disableFilter(array|string $filter): static
	{
		$this->isActiveFilterDisabled = true;
		foreach ((array) $filter as $_filter) {
			unset($this->filter[$_filter]);
		}
		return $this;
	}

	public function fetchPairs(?string $value, ?string $key): array
	{
		return ['value' => $value, 'key' => $key];
	}

	protected function innerJoin(QueryBuilder $qb, string $join, string $alias, ?string $conditionType = null, ?string $condition = null, ?string $indexBy = null): static
	{
		return $this;
	}

	protected function getPrimaryEntityAlias(): ?string
	{
		return null;
	}
}

/** Dotaz na entitu Order - nazev tridy urcuje, na jakou entitu se pta. */
final class OrderQuery extends QueryObjectStub
{
	use BaseQueryTrait;

	protected function applySecurityFilter(): void
	{
		$this->filter['securityFilter'] = fn() => null;
	}

	protected function applyAccountFilter(QueryBuilder $qb, Account $account): void
	{
	}
}

/** Dotaz, ktery navic filtruje podle aktivity. */
final class InvoiceQuery extends QueryObjectStub implements IsActiveFilter
{
	use BaseQueryTrait;

	protected function applySecurityFilter(): void
	{
	}

	protected function applyAccountFilter(QueryBuilder $qb, Account $account): void
	{
	}
}
