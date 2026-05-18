<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;
use DateTime;

interface SoftDeleteableInterface
{
	public function setIsDeleted(): static;
	public function getIsDeleted(): int;
	public function setDeletedAt(?DateTime $deletedAt = null): static;
	public function getDeletedAt(): ?DateTime;
	public function isDeleted(): bool;
	public function setDeletedBy(?Identity $deletedBy): static;
	public function getDeletedBy(): ?Identity;
}
