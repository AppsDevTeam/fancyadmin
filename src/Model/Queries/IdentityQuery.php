<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface IdentityQuery extends BaseQuery
{
	public function byUsername(string $username): static;
	public function byPhoneNumber(string $phoneNumber): static;
	public function byEmail(string $email): static;
	public function bySelectedAccount(Account $account): static;
	public function byEmailOrPhoneNumber(string $email, string $phoneNumber): static;
	public function byContext(?string $context): static;
}