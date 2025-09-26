<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;

interface IdentityQuery extends QueryObjectInterface
{
	public function byUsername(string $username): static;
	public function byPhoneNumber(string $phoneNumber): static;
}