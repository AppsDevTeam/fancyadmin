<?php

namespace ADT\FancyAdmin\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;
use Doctrine\ORM\QueryBuilder;

interface OnetimeTokenQuery extends QueryObjectInterface
{
	public function byToken(string $token): static;
	public function byType(string $type): static;
	public function byObjectId(int|array $objectId): static;
	public function byIsValid(bool $checkValidUntil = true): static;
	public function byIpAddress(string $ipAddress): static;
	public function byCreatedBetween(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): static;
}