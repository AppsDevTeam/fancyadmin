<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\OnetimeTokenQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Queries\Factories\OnetimeTokenQueryFactory;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Security\AuthenticationException;
use ReflectionException;

trait SignPresenterTrait
{
	use PresenterTrait;
	use RedirectAfterLoginTrait;
	use OnetimeTokenQueryFactoryInject;
	use EntityManagerInject;
	use FancyAdminInject;

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

	/**
	 * @throws ReflectionException
	 */
	public function actionToken(string $email, string $token, int $skipPasswordRecovery = 0): void
	{
		$this->getUser()->logout(true);

		try {
			$this->getUser()->login($email, $token, $this->_fancyAdmin->getCustomerAclResource());
		} catch (AuthenticationException $e) {
			bd($e);
			$this->flashMessageError($e->getMessage());
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		if (!$skipPasswordRecovery) {
			$this->redirect(':Portal:Sign:newPassword');
		} else {
			$this->_onetimeTokenQueryFactory->create()->byIsValid()->byToken($token)->byType(OnetimeToken::TYPE_LOGIN)->fetchOne()->setUsedAt(new DateTimeImmutable());
			$this->_em->flush();
		}
		$this->redirect('Home:');
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

	public function createComponentNewPasswordForm(NewPasswordFormFactory $factory): NewPasswordForm
	{
		return $factory->create();
	}

	public function createComponentLostPasswordForm(LostPasswordFormFactory $factory): LostPasswordForm
	{
		return $factory->create();
	}
}
