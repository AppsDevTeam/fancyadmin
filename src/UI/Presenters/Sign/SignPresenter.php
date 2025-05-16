<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\Model\Administration;
use ADT\FancyAdmin\Model\Entities\PasswordRecovery;
use ADT\FancyAdmin\UI\Presenters\BasePresenter;
use App\Model\Queries\Factories\PasswordRecoveryQueryFactory;
use ADT\FancyAdmin\UI\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\UI\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\UI\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Forms\SignIn\SignInFormFactory;
use Nette\Utils\Json;

class SignPresenter extends BasePresenter
{
	public function __construct(
		protected Administration $administration
	){
		parent::__construct();
	}

	public function startup(): void
	{
		parent::startup();

		if ($this->isLogged() && !in_array($this->getAction(), ['out'])) {
			$this->redirect($this->administration->getHomepagePresenter());
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

	public function renderIn(): void
	{
		$this->template->setFile(__DIR__ . '/in.latte');
	}

	public function actionOut(): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->getUser()->logout(true);
		}

		$this->redirect('in', ['redrawBody' => true]);
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
			$this->redirect(':Admin:Sign:in');
		}
	}

	public function renderNewPassword(): void
	{
		$this->template->setFile(__DIR__ . '/newPassword.latte');
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

	public function renderLostPassword(): void
	{
		$this->template->setFile(__DIR__ . '/lostPassword.latte');
	}
}
