<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\User;

interface CreatedByNullInterface
{
	public function setCreatedBy(?User $createdBy): static;
	public function getCreatedBy(): ?User;
}
