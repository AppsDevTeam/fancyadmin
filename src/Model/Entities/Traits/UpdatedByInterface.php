<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;

interface UpdatedByInterface
{
	public function setUpdatedBy(?Identity $updatedBy): static;
	public function getUpdatedBy(): ?Identity;
}
