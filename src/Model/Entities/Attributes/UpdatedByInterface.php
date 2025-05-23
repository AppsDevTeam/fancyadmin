<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\Identity;

interface UpdatedByInterface
{
	public function setUpdatedBy(?Identity $updatedBy): static;
	public function getUpdatedBy(): ?Identity;
}
