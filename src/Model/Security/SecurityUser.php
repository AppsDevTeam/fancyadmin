<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\Identity;
use Nette\Security\AuthenticationException;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use SensitiveParameter;

interface SecurityUser
{
	public function getId();
	/** @return ?Identity */
	public function getIdentity(): ?IIdentity;
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool;
	public function isAllowedFullDataAclResource(): bool;
	public function isLoggedIn(): bool;

	/**
	 * @throws AuthenticationException
	 */
	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void;
}
