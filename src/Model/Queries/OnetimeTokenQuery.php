<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use Doctrine\ORM\QueryBuilder;

interface OnetimeTokenQuery
{
	public function byToken(string $token): static;
	public function byType(string $type): static;
	public function byObjectId(int|array $objectId): static;
	public function byIsValid(bool $checkValidUntil = true): static;
	public function byIpAddress(string $ipAddress): static;
	public function byCreatedBetween(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): static;
}