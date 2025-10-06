<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\Identity;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use Nette\Security\Resource;
use SensitiveParameter;

interface SecurityUser
{
	public function getId();
	/** @return ?Identity */
	public function getIdentity(): ?IIdentity;
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool;
	public function isAllowedFullDataAclResource(): bool;
	public function isLoggedIn(): bool;
	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?Resource $context = null,
		array $metadata = []
	): void;
}
