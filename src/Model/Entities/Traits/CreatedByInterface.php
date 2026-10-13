<?php

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;

interface CreatedByInterface
{
	public function setCreatedBy(Identity $createdBy): static;
	public function getCreatedBy(): Identity;
}
