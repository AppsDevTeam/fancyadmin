<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

trait IdentityQueryTrait
{
	public function byUsername(string $username): static
	{
		return $this->by('username', $username);
	}

	public function byPhoneNumber(string $phoneNumber): static
	{
		return $this->by('phoneNumber', $phoneNumber);
	}
}
