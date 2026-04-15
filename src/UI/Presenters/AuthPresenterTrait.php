<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\LinkGeneratorInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\File;
use ADT\FancyAdmin\Model\FileUploadRules;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Menu\UserMenuFactory;
use ADT\FancyAdmin\UI\Components\Forms\SelectAccount\SelectAccountForm;
use ADT\FancyAdmin\UI\Components\Forms\SelectAccount\SelectAccountFormFactory;
use Nette\Application\AbortException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Http\FileUpload;
use Nette\Security\AuthenticationException;
use App\Model\Entities\Enums\AclResourceNameEnum;
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
				$this->_securityUser->login($token, context: $this->_fancyAdmin->getContext());
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
			if ($this->getUser()->getIdentity()->getSelectedAccount()?->getId() !== $this->getParameter('selectedAccount')) {
				$this->getUser()->getIdentity()->setSelectedAccount($this->_accountQueryFactory->create()->disableAccountFilter()->byId($this->getParameter('selectedAccount'))->fetchOne());
				$this->_em->flush();
			}
		} elseif ($this->getUser()->isAllowed($this->_fancyAdmin->getBackofficeAclResource())) {
			if ($this->getUser()->getIdentity()->getSelectedAccount()) {
				$this->getUser()->getIdentity()->setSelectedAccount(null);
				$this->_em->flush();
			}
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
			if (
				$this->isLinkCurrent($this->_fancyAdmin->getDefaultBackofficeRoute())
				&&
				!$this->getUser()->isAllowed($this->_fancyAdmin->getBackofficeAclResource())
			) {
				$this->redirect($this->_fancyAdmin->getDefaultCustomerRoute(), ['selectedAccount' => $this->getUser()->getIdentity()->getAccounts()[0]->getId()]);
			}

			if (!$this->validateSecurityAttributes()) {
				$this->validatePresenterPermission();
			}
		}
	}

	/**
	 * @throws ReflectionException
	 * @throws ForbiddenRequestException
	 * @return bool Whether a SecurityCheckAttribute was found and processed
	 */
	private function validateSecurityAttributes(): bool
	{
		$reflection = new ReflectionClass($this->getPresenter()::class);
		$reflectionMethod = $reflection->getMethod(static::ActionKey . ucfirst($this->getAction()));

		$found = false;
		foreach ($reflectionMethod->getAttributes() as $attribute) {
			if ($attribute->getName() === SecurityCheckAttribute::class) {
				$found = true;
				$attributeInstance = $attribute->newInstance();
				$aclResourceName = $attributeInstance->getResourceName();

				if (!$this->getUser()->isAllowed($aclResourceName)) {
					throw new ForbiddenRequestException();
				}
			}
		}

		return $found;
	}

	/**
	 * @throws ForbiddenRequestException
	 */
	private function validatePresenterPermission(): void
	{
		$parts = explode(':', $this->getName());
		$resource = lcfirst($parts[0]) . '.' . lcfirst($parts[1]);

		if (!$this->getUser()->isAllowed($resource)) {
			throw new ForbiddenRequestException();
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

	public function handleSummernoteUpload(): void
	{
		/** @var FileUpload[] $files */
		$files = $this->getHttpRequest()->getFiles();
		if (!isset($files['file'])) {
			$this->payload->status = false;
			$this->sendPayload();
		}

		$file = $files['file'];
		if (!$file->isOk() || $file->getSize() > FileUploadRules::MAX_FILE_SIZE || !in_array($file->getContentType(), FileUploadRules::ALLOWED_MIME_TYPES, true)) {
			$this->payload->status = false;
			$this->sendPayload();
		}

		$fileEntityClass = $this->_em->findEntityClassByInterface(File::class);
		/** @var File $fileEntity */
		$fileEntity = (new $fileEntityClass)
			->setTemporaryFile($file->getTemporaryFile(), $file->getUntrustedName());

		$this->_em->persist($fileEntity);
		$this->_em->flush();

		$this->payload->status = true;
		$this->payload->filename = $fileEntity->getUrl();
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
		$module = explode(':', $this->name)[0];
		$this->getTemplate()->navbarMenu = $navbarMenuFactory->create()
			->setLinkGenerator($this->_linkGenerator)
			->resolveAclResources($module);
		$this->getTemplate()->userMenu = $userMenuFactory->create()->setLinkGenerator($this->_linkGenerator);
		$this->getTemplate()->summernoteUpload = $this->getPresenter()->link('summernoteUpload!');
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
