<?php

namespace ADT\FancyAdmin\Model\Entities\Traits;

use App\Model\Entities\Identity;

interface CreatedByInterface
{
	public function setCreatedBy(Identity $createdBy): static;
	public function getCreatedBy(): Identity;
}
