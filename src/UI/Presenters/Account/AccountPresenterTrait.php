<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Account;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\ChangePasswordFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\PersonalDataFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Grids\Session\SessionGrid;
use ADT\FancyAdmin\UI\Components\Grids\Session\SessionGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;

trait AccountPresenterTrait
{
	use PresenterTrait;
	use SecurityUserInject;
	use AuthenticatorInject;
	use PersonalDataFormFactoryInject;
	use ChangePasswordFormFactoryInject;

	public function actionDefault(): void
	{
		$this->getTemplate()->identity = $this->_securityUser->getIdentity();
		$this->getTemplate()->setFile(__DIR__ . '/default.latte');
	}

	public function handleEditPersonalData(): void
	{
		$this->redrawSidePanel('personalData');
	}

	public function handleChangePassword(): void
	{
		$this->redrawSidePanel('changePassword');
	}

	public function handleLogoutAll(): never
	{
		$this->_authenticator->clearIdentity(
			$this->_securityUser->getIdentity()->getAuthObjectId()
		);
		$this->getUser()->logout(true);
		$this->redirect(':Portal:Sign:in');
	}

	public function createComponentPersonalDataSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		return $factory->create()
			->setFormFactory(fn() => $this->_personalDataFormFactory->create()
				->setEntity($this->_securityUser->getIdentity()));
	}

	public function createComponentChangePasswordSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		return $factory->create()
			->setFormFactory(fn() => $this->_changePasswordFormFactory->create()
				->setEntity($this->_securityUser->getIdentity()));
	}

	public function createComponentSessionGrid(SessionGridFactory $factory): SessionGrid
	{
		return $factory->create();
	}
}
