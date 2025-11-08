<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;

trait SignPresenterTrait
{
	use PresenterTrait;
	use RedirectAfterLoginTrait;
	use OnetimeTokenQueryFactoryInject;
	use EntityManagerInject;
	use FancyAdminInject;
	use SecurityUserInject;

	public function startup(): void
	{
		parent::startup();

		if ($this->getUser()->isLoggedIn() && !in_array($this->getAction(), ['out', 'token', 'newPassword'])) {
			$this->redirectAfterLogin();
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

	public function actionOut(): never
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->getUser()->logout(true);
		}

		$this->redirect('in', ['do' => 'redrawBody']);
	}

	public function actionNewPassword(): void
	{
		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect('in');
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
}
