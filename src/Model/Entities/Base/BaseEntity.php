<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Base;

use ADT\DoctrineForms\Entity;
use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Security\SecurityUser;

class BaseEntity implements Entity
{
	use Identifier;

	protected SecurityUser $securityUser;

	public function isNew(): bool
	{
		return !$this->getId();
	}

	public function setSecurityUser(SecurityUser $securityUser): static
	{
		$this->securityUser = $securityUser;
		return $this;
	}
}
