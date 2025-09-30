<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries\Abstract;

use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use ADT\FancyAdmin\Model\Security\SecurityUser;

interface BaseQuery extends QueryObjectInterface
{
	const string SECURITY_FILTER = "securityFilter";
	const string ACCOUNT_FILTER = "accountFilter";

	public function init(): void;
	public function setSecurityUser(SecurityUser $securityUser): static;
	public function disableSecurityFilter(): static;
	public function disableAccountFilter(): static;
	public function fetchPairs(?string $value = 'name', ?string $key = 'id'): array;
	public function byIdNot(int|array $id): static;
}
