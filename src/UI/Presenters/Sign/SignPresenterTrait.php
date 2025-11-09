<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenQueryFactoryInject;
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
	use OnetimeTokenQueryFactoryInject;
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
			$this->flashMessageError('_app.modules.web.presenters.sign.flashFraud');
		}
	}

	public function actionOut(): never
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->getUser()->logout(true);
		}

		$this->redirect('in', ['do' => 'redrawBody']);
	}

	public function actionNewPassword(): void
	{
		try {
			$this->identity = $this->_authenticator->authenticate($this->getParameter('token'));
		} catch(AuthenticationException) {
			$this->flashMessageError('Odkaz již není platný. Pro nový odkaz klikněte <a href="' .  $this->link(':Portal:Sign:lostPassword') . '">zde</a>.'); // TODO translate
			$this->redirect(':Portal:Sign:in');
		}
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
		return $factory->create($this->identity);
	}
}
