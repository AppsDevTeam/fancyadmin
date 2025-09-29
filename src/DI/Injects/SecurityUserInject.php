<?php

namespace ADT\FancyAdmin\DI\Injects;

use ADT\FancyAdmin\Model\Security\SecurityUser;

trait SecurityUserInject
{
	protected SecurityUser $_securityUser;
	public function injectSecurityUser(SecurityUser $securityUser): void
	{
		$this->_securityUser = $securityUser;
	}
}