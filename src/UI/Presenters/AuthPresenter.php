<?php

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Latte\RedrawSidePanel;
use Nette\Application\Attributes\Persistent;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\InvalidLinkException;
use ReflectionClass;
use ReflectionException;

abstract class AuthPresenter extends BasePresenter
{
	use RedrawSidePanel;

	#[Persistent]
	public ?string $gridFilterClass = null;

	#[Persistent]
	public array $gridFilterParameters = [];

	protected $entity = null;

	/**
	 * @throws InvalidLinkException
	 */
	protected function startup(): void
	{
		parent::startup();

		if (!$this->isLogged()) {
			$this->redirect(':Admin:Sign:in');
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
		if ($this->securityUser->isLoggedIn()) {
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

	public function handleEdit(Identity $identity): void
	{
		$this->entity = $identity;
		$this->redrawSidePanel('identity');
	}

	public function handleNew(): void
	{
		$this->redrawSidePanel('identity');
	}

	public function handleRemoveGridFilter(): void
	{
	}

	public function afterRender(): void
	{
		if (!$this->isControlInvalid()) {
			$this->redrawControl('title');
			$this->redrawControl('sideMenu');
			if ($this->getParameter('redrawBody')) {
				$this->redrawControl('body');
			} else {
				$this->redrawControl('container');
				if ($this->getFlashSession()->get('flash')) {
					$this->redrawControl('flashes');
				}
			}
		}
	}
}
