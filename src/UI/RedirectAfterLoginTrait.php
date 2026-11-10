<?php

namespace ADT\FancyAdmin\UI;

use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\ReturnPathInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Application\UI\Presenter;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Routing\Router;

trait RedirectAfterLoginTrait
{
	use SecurityUserInject;
	use FancyAdminInject;
	use ReturnPathInject;

	#[Autowire]
	protected Router $_router;

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
		if (($returnUrl = $this->_returnPath->consume()) && $this->isReturnUrlAllowed($returnUrl)) {
			$this->getPresenter()->redirectUrl($returnUrl);
		}

		if ($this->_fancyAdmin->isMultiTenant() && ($selectedAccount = $this->_securityUser->getIdentity()->getSelectedAccount())) {
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultCustomerRoute(), ['selectedAccount' => $selectedAccount->getId()]);
		} else {
			$this->getPresenter()->redirect($this->_fancyAdmin->getDefaultBackofficeRoute());
		}
	}

	/**
	 * Cíl uložil kdokoli (cookie není svázaná s identitou) - typicky stránka předchozího
	 * uživatele, ze které po odhlášení ještě odešel požadavek. Bez práva by přihlášený
	 * skončil na 403 (pod AJAXem jen s chybovou hláškou), proto se backoffice cíl ověří
	 * stejně jako AuthPresenterTrait::validatePresenterPermission a bez práva se jde na
	 * výchozí stránku. Cíle mimo backoffice si oprávnění ověřují samy.
	 */
	private function isReturnUrlAllowed(string $url): bool
	{
		$current = $this->getPresenter()->getHttpRequest()->getUrl();
		$params = $this->_router->match(new Request(new UrlScript($url, $current->getScriptPath())));
		if ($params === null) {
			return false;
		}

		$parts = explode(':', (string) ($params['presenter'] ?? ''));
		$backofficeModule = explode(':', ltrim($this->_fancyAdmin->getDefaultBackofficeRoute(), ':'))[0];
		if (count($parts) < 2 || $parts[0] !== $backofficeModule || end($parts) === 'Profile') {
			return true;
		}

		return $this->_securityUser->isAllowed(lcfirst($parts[0]) . '.' . lcfirst($parts[1]));
	}
}
