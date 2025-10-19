<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Account;

trait IdentityQueryTrait
{
	public function byUsername(string $username): static
	{
		return $this->by('username', $username);
	}

	public function byEmail(string $email): static
	{
		return $this->by('email', $email);
	}

	public function byPhoneNumber(string $phoneNumber): static
	{
		return $this->by('phoneNumber', $phoneNumber);
	}

	public function bySelectedAccount(Account $account): static
	{
		return $this->by('selectedAccount', $account);
	}
}
