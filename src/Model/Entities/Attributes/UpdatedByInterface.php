<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\User;

interface UpdatedByInterface
{
	public function setUpdatedBy(?User $updatedBy): static;
	public function getUpdatedBy(): ?User;
}
