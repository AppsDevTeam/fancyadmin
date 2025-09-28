<?php

namespace ADT\FancyAdmin\Model\Security;

use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use SensitiveParameter;

/**
 * @method \ADT\FancyAdmin\Model\Entities\Identity getIdentity()
 */
interface SecurityUser
{
	public function getIdentity(): ?IIdentity;
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool;
	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void;
}
