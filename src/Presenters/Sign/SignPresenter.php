<?php

namespace ADT\FancyAdmin\Presenters\Sign;

use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use ADT\FancyAdmin\Presenters\BasePresenter;
use App\Model\Queries\Factories\PasswordRecoveryQueryFactory;
use ADT\FancyAdmin\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\Forms\SignIn\SignInFormFactory;
use Kdyby\Autowired\Attributes\Autowire;

class SignPresenter extends BasePresenter
{
	#[Autowire]
	protected PasswordRecoveryQueryFactory $passwordRecoveryQueryFactory;

	protected PasswordRecovery $passwordRecovery;

	public function startup(): void
	{
		parent::startup();

		if ($this->isLogged() && !in_array($this->getAction(), ['out'])) {
			$this->redirect('Dashboard:');
		}
	}

	public function actionIn(?string $errorMsg): void
	{
		if ($errorMsg) {
			$this->flashMessageError($errorMsg);
		}

		if ($this->getParameter('fraudDetected')) {
			$this->flashMessageError('_app.modules.web.presenters.sign.flashFraud');
		}
	}

	public function actionOut(): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->getUser()->logout(true);
		}

		$this->redirect('in', ['redrawBody' => true]);
	}

	public function actionResetPassword(): void
	{
	}

	public function actionNewPassword(string $token): void
	{
		if ($passwordRecovery = $this->passwordRecoveryQueryFactory->create()->byToken($token)->fetchOneOrNull()) {
			$this->passwordRecovery = $passwordRecovery;
		} else {
			$this->flashMessageError('_app.modules.web.presenters.sign.flashWrongToken');
			$this->redirect('in', ['errorMsg' => 'Wrong token']);
		}

		if (! $this->passwordRecovery->isValid()) {
			$this->flashMessageError('app.forms.signIn.errors.expiredRecovery');
			$this->redirect(':Portal:Sign:in');
		}
	}


	public function beforeRender(): void
	{
		parent::beforeRender();

		$this->template->bodyClass = 'bg-primary-variant';
		$this->template->bodyBackgroundImageUrl = null;
	}

	public function createComponentLoginForm(SignInFormFactory $factory): SignInForm
	{
		return $factory->create();
	}

	public function createComponentNewPasswordForm(NewPasswordFormFactory $factory): NewPasswordForm
	{
		return $factory->create()
			->setPasswordRecovery($this->passwordRecovery);
	}

	public function createComponentLostPasswordForm(LostPasswordFormFactory $factory): LostPasswordForm
	{
		return $factory->create();
	}


	public function actionLostPassword(): void
	{
	}
}
