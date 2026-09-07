<?php

namespace ADT\FancyAdmin\UI;

use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\ReturnPathInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use Nette\Application\UI\Presenter;

trait RedirectAfterLoginTrait
{
	use SecurityUserInject;
	use FancyAdminInject;
	use ReturnPathInject;

	abstract public function getPresenter(): ?Presenter;

	protected function redirectAfterLogin(): never
	{
		// Fancyadmin sám session backlink nezakládá - nepřihlášenému se cíl pamatuje
		// v cookie, aby mu nevznikala session (viz AuthPresenterTrait::startup).
		// Zpracovat ho ale umíme dál, aby si aplikace mohla storeRequest() zavolat sama
		// tam, kde opravdu potřebuje zopakovat POST. Prázdný backlink se ani nezkouší,
		// ať kvůli němu session nevznikne aspoň tady.
		if ($this->getPresenter()->backlink !== '') {
			$this->getPresenter()->restoreRequest($this->getPresenter()->backlink);
		}

		// Cíl z cookie není svázaný s identitou (na rozdíl od session backlinku), takže
		// se na něm nesmí stavět autorizace - cílová akce si musí ověřit sama, že na ni
		// přihlášený uživatel má právo.
		if ($returnUrl = $this->_returnPath->consume()) {
			$this->getPresenter()->redirectUrl($returnUrl);
		}

		if ($selectedAccount = $this->_securityUser->getIdentity()->getSelectedAccount()) {
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultCustomerRoute(), ['selectedAccount' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultBackofficeRoute());
		}
	}
}
