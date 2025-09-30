<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;

interface OnetimeTokenQuery extends BaseQuery
{
	public function byToken(string $token): static;
	public function byType(string $type): static;
	public function byObjectId(int|array $objectId): static;
	public function byIsValid(bool $checkValidUntil = true): static;
	public function byIpAddress(string $ipAddress): static;
	public function byCreatedBetween(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): static;
}