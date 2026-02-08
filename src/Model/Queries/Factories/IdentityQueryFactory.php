<?php

namespace ADT\FancyAdmin\Model\Queries\Factories;

use ADT\FancyAdmin\Model\Queries\IdentityQuery;

interface IdentityQueryFactory extends \ADT\DoctrineAuthenticator\OTP\IdentityQueryFactory
{
	public function create(): \ADT\DoctrineAuthenticator\OTP\IdentityQuery;
}