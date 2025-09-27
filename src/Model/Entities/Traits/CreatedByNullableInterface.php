<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;

interface CreatedByNullableInterface
{
	public function setCreatedBy(?Identity $createdBy): static;
	public function getCreatedBy(): ?Identity;
}
