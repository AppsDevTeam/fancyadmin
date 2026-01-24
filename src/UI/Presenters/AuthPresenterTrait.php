<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\LinkGeneratorInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Menu\UserMenuFactory;
use ADT\FancyAdmin\UI\Components\Forms\SelectAccount\SelectAccountForm;
use ADT\FancyAdmin\UI\Components\Forms\SelectAccount\SelectAccountFormFactory;
use Nette\Application\AbortException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\AuthenticationException;
use ReflectionClass;
use ReflectionException;

trait AuthPresenterTrait
{
	use PresenterTrait;
	use EntityManagerInject;
	use LinkGeneratorInject;
	use AccountQueryFactoryInject;
	use FancyAdminInject;
	use AuthenticatorInject;
	use SecurityUserInject;

	#[Persistent]
	public ?int $selectedAccount = null;

	#[Persistent]
	public ?string $gridFilterClass = null;

	#[Persistent]
	public array $gridFilterParameters = [];

	/**
	 * @throws InvalidLinkException
	 */
	protected function startup(): void
	{
		parent::startup();

		if ($token = $this->getParameter('token')) {
			try {
				$this->_securityUser->login($token, context: 'cashdesk'); // TODO enum
				$this->redirect('this');
			} catch (AuthenticationException) {}
		}

		if (!$this->getUser()->isLoggedIn()) {
			$parameters = array_merge($this->request->getParameters());
			unset($parameters['token']);

			$this->request->setParameters(array_merge($this->request->getParameters()));
			$this->redirect(':Portal:Sign:in', ['backlink' => $this->storeRequest()]);
		}

		if ($this->getParameter('selectedAccount')) {
			$this->getUser()->getIdentity()->setSelectedAccount($this->_accountQueryFactory->create()->disableAccountFilter()->byId($this->getParameter('selectedAccount'))->fetchOne());
		} elseif ($this->getUser()->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			$this->getUser()->getIdentity()->setSelectedAccount(null);
		} elseif ($this->getUser()->getIdentity()->getSelectedAccount()) {
			// TODO odstranit
			try {
				$this->redirect(':PortalCustomer:Home:', ['selectedAccount' => $this->getUser()->getIdentity()->getSelectedAccount()->getId()]);
			} catch (InvalidLinkException) {
				$this->redirect(':Portal:Home:', ['selectedAccount' => $this->getUser()->getIdentity()->getSelectedAccount()->getId()]);
			}
		} else {
			// TODO odstranit
			try {
				$this->redirect(':PortalCustomer:Home:', ['selectedAccount' => $this->getUser()->getIdentity()->getAccounts()[0]->getId()]);
			} catch (InvalidLinkException) {
				$this->redirect(':Portal:Home:', ['selectedAccount' => $this->getUser()->getIdentity()->getAccounts()[0]->getId()]);
			}
		}

		// TODO delame kvuli ublaboo datagridu ktery potrebuje sessionu uz pri vykresleni
		$this->getSession()->start();

		$this->primaryTemplate = true;
	}

	/**
	 * @throws ReflectionException
	 * @throws ForbiddenRequestException
	 */
	public function checkRequirements($element): void
	{
		parent::checkRequirements($element);
		if ($this->getUser()->isLoggedIn()) {
			$this->validateSecurityAttributes();
		}
	}

	/**
	 * @throws ReflectionException
	 * @throws ForbiddenRequestException
	 */
	private function validateSecurityAttributes(): void
	{
		$reflection = new ReflectionClass($this->getPresenter()::class);
		$reflectionMethod = $reflection->getMethod(static::ActionKey . ucfirst($this->getAction()));

		foreach ($reflectionMethod->getAttributes() as $attribute) {
			if ($attribute->getName() === SecurityCheckAttribute::class) {
				$attributeInstance = $attribute->newInstance();
				$aclResourceName = $attributeInstance->getResourceName();

				if (!$this->getUser()->isAllowed($aclResourceName)) {
					throw new ForbiddenRequestException();
				}
			}
		}
	}

	public function handleEditGridFilter(): void
	{
		$this->redrawSidePanel('gridFilter');
	}

	public function handleRemoveGridFilter(): void
	{
		if ($gridFilter = $this->gridFilterQueryFactory->create()->byId($this->getParameter('removeId'))->fetchOneOrNull()) {
			$this->_em->remove($gridFilter);
			$this->_em->flush();
		}
	}

	public function beforeRender(): void
	{
		parent::beforeRender();
		$submodule = str_replace('Portal', '', explode(':', $this->name)[0]);
		$className = "\\App\\UI\\Portal\\{$submodule}\\Presenters\\NavbarMenuFactory";
		$userMenuClassName = "\\App\\UI\\Portal\\{$submodule}\\Presenters\\UserMenuFactory";
		/** @var NavbarMenuFactory $className */
		$navbarMenuFactory = new $className();
		/** @var UserMenuFactory $userMenuFactory */
		$userMenuFactory = new $userMenuClassName();
		$this->getTemplate()->navbarMenu = $navbarMenuFactory->create()->setLinkGenerator($this->_linkGenerator);
		$this->getTemplate()->userMenu = $userMenuFactory->create()->setLinkGenerator($this->_linkGenerator);
	}

	/**
	 * @throws AbortException
	 */
	public function redrawSidePanel(?string $name = null): never
	{
		$this->getPresenter()->payload->snippets[$this->getSnippetId('sidePanel')] = $this[$name ? $name . ucfirst('sidePanel') : 'sidePanel']->renderToString();
		$this->getPresenter()->sendPayload();
	}

	public function createComponentSelectAccountForm(SelectAccountFormFactory $factory): SelectAccountForm
	{
		return $factory->create();
	}
}
