<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use Nette\Application\Attributes\Persistent;
use Nette\Security\AuthenticationException;

trait SignPresenterTrait
{
	use PresenterTrait;
	use RedirectAfterLoginTrait;
	use EntityManagerInject;
	use FancyAdminInject;
	use SecurityUserInject;
	use AuthenticatorInject;

	#[Persistent]
	public ?string $token = null;

	protected Identity $identity;

	public function actionIn(?string $errorMsg): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->redirectAfterLogin();
		}

		if ($errorMsg) {
			$this->flashMessageError($errorMsg);
		}

		if ($this->getParameter('fraudDetected')) {
			$this->flashMessageError('_fcadmin.modules.web.presenters.sign.flashFraud');
		}
	}

	public function actionOut(): never
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->getUser()->logout(true);
		}

		$this->redirect('in');
	}

	public function actionOutAll(): never
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->_authenticator->clearIdentity(
				$this->getUser()->getIdentity()->getAuthObjectId()
			);
			$this->getUser()->logout(true);
		}

		$this->redirect('in');
	}

	public function actionNewPassword(string $token): void
	{
		try {
			$this->identity = $this->_authenticator->authenticate($token);
		} catch(AuthenticationException) {
			$this->flashMessageError('Odkaz již není platný. Pro vygenerováno nového odešlete znovu formulář.'); // TODO translate
			$this->redirect(':Portal:Sign:lostPassword');
		}
	}

	public function actionPasswordSet(): void
	{
		$this->template->canContinue = $this->getUser()->isLoggedIn();
	}

	public function handleContinue(): void
	{
		$this->redirectAfterLogin();
	}

	public function actionLostPassword(): void
	{
	}

	public function createComponentSignInForm(SignInFormFactory $factory): SignInForm
	{
		return $factory->create();
	}

	public function createComponentLostPasswordForm(LostPasswordFormFactory $factory): LostPasswordForm
	{
		return $factory->create();
	}

	public function createComponentNewPasswordForm(NewPasswordFormFactory $factory): NewPasswordForm
	{
		return $factory->create()->setEntity($this->identity);
	}
}
