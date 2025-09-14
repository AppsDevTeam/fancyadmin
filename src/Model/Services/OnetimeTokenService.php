<?php

namespace ADT\FancyAdmin\Model\Services;

use ADT\FancyAdmin\Model\Entities\Identity;
use DateTimeImmutable;

interface OnetimeTokenService
{
	public function generateToken(Identity $identity, DateTimeImmutable $validInHours);
}