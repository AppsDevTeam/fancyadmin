<?php

namespace ADT\FancyAdmin\UI;

use ADT\FancyAdmin\Model\Security\SecurityUser;

trait RedirectAfterLoginTrait
{
	private SecurityUser $_securityUser;
	public function injectSecurityUser(SecurityUser $securityUser)
	{
		$this->_securityUser = $securityUser;
	}
	
	protected function redirectAfterLogin()
	{
		if ($selectedAccount = $this->_securityUser->getIdentity()->getSelectedAccount()) {
			$this->getPresenter()->redirect('Customer:Home:', ['do' => 'redrawBody', 'selectedCompany' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect('Backoffice:Home:', ['do' => 'redrawBody']);
		}
	}
}