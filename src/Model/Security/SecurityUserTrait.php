<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\AclRole;
use Nette\Security\Authorizator;

trait SecurityUserTrait
{
	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool
	{
		return array_any(
			$this->getIdentity()->getRoles(),
			fn(AclRole $role) => $role->getIsAdmin() || $this->getAuthorizator()->isAllowed($role->getRoleId(), $resource, $privilege)
		);
	}
}
