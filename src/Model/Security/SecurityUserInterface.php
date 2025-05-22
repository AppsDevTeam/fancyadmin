<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\IdentityInterface;
use Nette\Security\AuthenticationException;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;

interface SecurityUserInterface
{
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool;
	public function login(
		string|IIdentity $username,
		#[\SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void;
}
