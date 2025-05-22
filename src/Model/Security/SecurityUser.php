<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\AclRoleInterface;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\IdentityInterface;
use Nette\Security\AuthenticationException;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;

trait SecurityUser
{
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool
	{
		return array_any(
			$this->getIdentity()->getRoles(),
			fn(AclRoleInterface $role) => $role->getIsAdmin() || $this->getAuthorizator()->isAllowed($role->getRoleId(), $resource, $privilege)
		);
	}

	/**
	 * @throws AuthenticationException
	 */
	public function login(
		string|IIdentity $username,
		#[\SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void
	{
		parent::login($username, $password, $context, $metadata);
	}
}
