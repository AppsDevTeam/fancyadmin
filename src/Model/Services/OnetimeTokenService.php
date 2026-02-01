<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use DateTimeImmutable;

interface OnetimeTokenService
{
	public function generateToken(OnetimeTokenType $type, DateTimeImmutable $validUntil, ?string $objectClass = null, ?int $objectId = null, ?int $length = 32, ?string $charList = 'a-zA-Z0-9'): OnetimeToken;
	public function findToken(OnetimeTokenType $type, string $token): ?OnetimeToken;
}