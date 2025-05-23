<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\Identity;

interface CreatedByNullInterface
{
	public function setCreatedBy(?Identity $createdBy): static;
	public function getCreatedBy(): ?Identity;
}
