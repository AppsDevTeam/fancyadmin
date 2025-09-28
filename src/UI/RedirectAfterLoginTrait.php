<?php

namespace ADT\FancyAdmin\UI;

use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use Nette\Application\UI\Presenter;

trait RedirectAfterLoginTrait
{
	use SecurityUserInject;

	abstract public function getPresenter(): ?Presenter;
	
	protected function redirectAfterLogin(): never
	{
		if ($selectedAccount = $this->_securityUser->getIdentity()->getSelectedAccount()) {
			$this->getPresenter()->redirect('Customer:Home:', ['do' => 'redrawBody', 'selectedCompany' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect('Backoffice:Home:', ['do' => 'redrawBody']);
		}
	}
}