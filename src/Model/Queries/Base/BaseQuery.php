<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Base;

use ADT\Components\AjaxSelect\Interfaces\OrByIdFilterInterface;
use ADT\Components\AjaxSelect\Traits\OrByIdFilterTrait;
use ADT\DoctrineComponents\QueryObject;
use ADT\DoctrineComponents\QueryObjectByMode;
use ADT\FancyAdmin\Model\Queries\Filters\IsActiveInterface;
use ADT\FancyAdmin\Model\Security\SecurityUser;

/**
 * @extends QueryObject<TEntity>
 * @template TEntity of object
 */
abstract class BaseQuery extends QueryObject implements OrByIdFilterInterface
{
	use OrByIdFilterTrait;

	const string SECURITY_FILTER = "securityFilter";

	abstract protected function applySecurityFilter(): void;

	protected SecurityUser $securityUser;

	public function init(): void
	{
		parent::init();

		$this->applySecurityFilter();

		if ($this instanceof IsActiveInterface) {
			$this->filter[IsActiveInterface::IS_ACTIVE_FILTER] = fn() => $this->byIsActive();
		}
	}

	/**
	 * @return string
	 */
	public function getEntityClass(): string
	{
		$fullClassQueryName = get_class($this);
		$fullClassEntityName = str_replace("Queries", "Entities", $fullClassQueryName);
		$fullClassEntityName = str_replace("Query", "", $fullClassEntityName);

		return $fullClassEntityName;
	}

	public function setSecurityUser(SecurityUser $securityUser): static
	{
		$this->securityUser = $securityUser;
		return $this;
	}

	public function disableSecurityFilter(): static
	{
		unset($this->filter[self::SECURITY_FILTER]);
		return $this;
	}

	public function fetchPairs(?string $value = 'name', ?string $key = 'id'): array
	{
		return parent::fetchPairs($value, $key);
	}

	public function byIdNot(int|array $id): static
	{
		if (!$id) {
			return $this;
		}

		return $this->by('id', $id, QueryObjectByMode::NOT_IN_ARRAY);
	}

	public function byId($id): static
	{
		if ($this instanceof IsActiveInterface) {
			$this->disableIsActiveFilter();
		}

		return parent::byId($id);
	}

	final protected function addFilter(callable $callback, ?string $name = null): static
	{
		if ($name) {
			$this->filter[$name] = $callback;
		} else {
			$this->filter[] = $callback;
		}
		return $this;
	}

	protected function getSecurityUser(): SecurityUser
	{
		return $this->securityUser;
	}
}
