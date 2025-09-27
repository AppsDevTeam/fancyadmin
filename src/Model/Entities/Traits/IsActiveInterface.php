<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

interface IsActiveInterface
{
	public function getIsActive(): bool;
	public function setIsActive(bool $isActive): static;
}