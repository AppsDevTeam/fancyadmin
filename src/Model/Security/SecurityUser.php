<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\Identity;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use SensitiveParameter;

interface SecurityUser
{
	public function getId();
	public function getIdentity(): Identity;
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool;
	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void;
}
