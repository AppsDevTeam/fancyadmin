<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Abstract\Factories\BaseQuery;

interface IdentityQuery extends BaseQuery
{
	public function byUsername(string $username): static;
	public function byPhoneNumber(string $phoneNumber): static;
}