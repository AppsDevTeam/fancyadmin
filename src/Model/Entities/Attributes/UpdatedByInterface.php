<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IdentityInterface;

interface UpdatedByInterface
{
	public function setUpdatedBy(?IdentityInterface $updatedBy): static;
	public function getUpdatedBy(): ?IdentityInterface;
}
