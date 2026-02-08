<?php

namespace ADT\FancyAdmin\Model\Security;

use Nette\Security\IIdentity;

interface Authenticator
{
	public function findIdentity(string $identifier, ?string $context = null, array $metadata = []): ?IIdentity;
	public function authenticate(string $username, ?string $password = null, ?string $context = null, array $metadata = []): IIdentity;
}