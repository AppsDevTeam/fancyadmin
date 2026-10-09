<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use DateTimeInterface;

interface AuthLogQuery extends QueryObjectInterface
{
	public function byType(string $type): static;
	public function byIdentity(string $identity): static;
	public function byObjectId(string $objectId): static;
	public function byIp(string $ip): static;
	public function byDateFrom(DateTimeInterface $from): static;
	public function byDateTo(DateTimeInterface $to): static;
}
