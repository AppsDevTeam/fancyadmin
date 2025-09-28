<?php

namespace ADT\FancyAdmin\UI;

use ADT\FancyAdmin\Model\Security\SecurityUser;

trait RedirectAfterLoginTrait
{
	private SecurityUser $_securityUser;
	protected function injectSecurityUser(SecurityUser $securityUser)
	{
		$this->_securityUser = $securityUser;
	}
	
	protected function redirectAfterLogin()
	{
		if ($selectedAccount = $this->_securityUser->getIdentity()->getSelectedAccount()) {
			$this->getPresenter()->redirect('Home:default', ['do' => 'redrawBody', 'selectedCompany' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect('Dashboard:default', ['do' => 'redrawBody']);
		}
	}
}