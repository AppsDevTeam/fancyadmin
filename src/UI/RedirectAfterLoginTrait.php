<?php

namespace ADT\FancyAdmin\UI;

use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use Nette\Application\UI\Presenter;

trait RedirectAfterLoginTrait
{
	use SecurityUserInject;
	use FancyAdminInject;

	abstract public function getPresenter(): ?Presenter;
	
	protected function redirectAfterLogin(): never
	{
		$this->getPresenter()->restoreRequest($this->getPresenter()->backlink);

		if ($selectedAccount = $this->_securityUser->getIdentity()->getSelectedAccount()) {
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultCustomerRoute(), ['selectedAccount' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultBackofficeRoute());
		}
	}
}