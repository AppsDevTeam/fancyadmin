<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IdentityInterface;

interface CreatedByNullInterface
{
	public function setCreatedBy(?IdentityInterface $createdBy): static;
	public function getCreatedBy(): ?IdentityInterface;
}
