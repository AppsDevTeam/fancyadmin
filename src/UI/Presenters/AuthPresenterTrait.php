<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\LinkGeneratorInject;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use Nette\Application\AbortException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\InvalidLinkException;
use ReflectionClass;
use ReflectionException;

trait AuthPresenterTrait
{
	use PresenterTrait;
	use EntityManagerInject;
	use LinkGeneratorInject;
	
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

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Portal:Sign:in');
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
		$submodule = explode(':', $this->name)[1];
		$className = "\\App\\UI\\Portal\\{$submodule}\\Presenters\\NavbarMenuFactory";
		/** @var NavbarMenuFactory $className */
		$navbarMenuFactory = new $className();
		$this->getTemplate()->navbarMenu = $navbarMenuFactory->create()->setLinkGenerator($this->_linkGenerator);
	}

	public function afterRender(): void
	{
		if (!$this->isControlInvalid()) {
			$this->redrawControl('title');
			$this->redrawControl('sideMenu');
			$this->redrawControl('container');
			$this->redrawControl('sidePanelContainer');
			if ($this->getFlashSession()->get('flash')) {
				$this->redrawControl('flashes');
			}
		}
	}

	/**
	 * @throws AbortException
	 */
	public function redrawSidePanel(?string $name = null): never
	{
		$this->getPresenter()->payload->snippets[$this->getSnippetId('sidePanel')] = $this[$name ? $name . ucfirst('sidePanel') : 'sidePanel']->renderToString();
		$this->getPresenter()->sendPayload();
	}
}
