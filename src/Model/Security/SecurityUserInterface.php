<?php

namespace ADT\FancyAdmin\Model\Security;

use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use SensitiveParameter;

interface SecurityUserInterface
{
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool;
	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void;
}
