<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IUser;
use ADT\FancyAdmin\Model\Entities\User;

interface UpdatedByInterface
{
	public function setUpdatedBy(?IUser $updatedBy): static;
	public function getUpdatedBy(): ?IUser;
}
