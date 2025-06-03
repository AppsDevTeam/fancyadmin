<?php

namespace ADT\FancyAdmin\Model\Queries\Filters;

trait IsActiveFilterTrait
{
	public function byIsActive(bool $isActive = true): static
	{
		return $this->by("isActive", $isActive);
	}

	public function disableIsActiveFilter(): static
	{
		$this->disableFilter(self::IS_ACTIVE_FILTER);
		return $this;
	}
}
