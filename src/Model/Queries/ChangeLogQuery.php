<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;

interface ChangeLogQuery extends QueryObjectInterface
{
	public function byAction(string $action): static;
	public function byObjectClass(string $objectClass): static;
	public function byObjectId(int $objectId): static;
	public function byIdentityId(int $identityId): static;
	public function byDateFrom(\DateTimeInterface $from): static;
	public function byDateTo(\DateTimeInterface $to): static;
}
