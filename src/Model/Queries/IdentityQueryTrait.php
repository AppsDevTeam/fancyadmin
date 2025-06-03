<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Filters\IsActiveFilterTrait;

trait IdentityQueryTrait
{
	use IsActiveFilterTrait;

	public function setDefaultOrder(): void
	{
		$this->orderBy(['firstName' => 'ASC', 'lastName' => 'ASC']);
	}

	public function byEmail(string $email): static
	{
		return $this->by('email', $email);
	}

	public function byPhoneNumber(string $phoneNumber): static
	{
		return $this->by('phoneNumber', $phoneNumber);
	}
}
