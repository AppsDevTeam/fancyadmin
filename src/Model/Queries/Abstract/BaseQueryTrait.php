<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Abstract;

use ADT\Components\AjaxSelect\Traits\OrByIdFilterTrait;
use ADT\DoctrineComponents\QueryObject\Filters\IsActiveFilter;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\FancyAdmin\Model\Security\SecurityUser;

trait BaseQueryTrait
{
	use OrByIdFilterTrait;

	abstract protected function applySecurityFilter(): void;

	protected SecurityUser $securityUser;

	public function init(): void
	{
		parent::init();

		$this->applySecurityFilter();

		if ($this instanceof IsActiveFilter) {
			$this->filter[IsActiveFilter::IS_ACTIVE_FILTER] = fn() => $this->byIsActive();
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
		unset($this->filter[BaseQuery::SECURITY_FILTER]);
		return $this;
	}

	public function disableAccountFilter(): static
	{
		unset($this->filter[BaseQuery::ACCOUNT_FILTER]);
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
		if ($this instanceof IsActiveFilter) {
			$this->disableIsActiveFilter();
		}

		return parent::byId($id);
	}
}
