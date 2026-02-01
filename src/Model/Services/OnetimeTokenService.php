<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use DateTimeImmutable;

interface OnetimeTokenService
{
	public function saveToken(OnetimeTokenType $type, DateTimeImmutable $validUntil, ?Entity $entity = null, ?string $token = null): OnetimeToken;
	public function findToken(OnetimeTokenType $type, string $token): ?OnetimeToken;
}