<?php

namespace App\UI\Portal\Presenters\Sign;

use App\Model\Doctrine\EntityManager;
use App\Model\Entities\Company;
use App\Model\Entities\OnetimeToken;
use App\Model\Enums\AclResourceEnum;
use App\Model\Queries\Factories\CompanyQueryFactory;
use App\Model\Queries\Factories\OnetimeTokenQueryFactory;
use App\Model\Security\Authenticator;
use App\UI\Portal\Components\Forms\LostPassword\LostPasswordForm;
use App\UI\Portal\Components\Forms\LostPassword\LostPasswordFormFactory;
use App\UI\Portal\Components\Forms\NewPassword\NewPasswordForm;
use App\UI\Portal\Components\Forms\NewPassword\NewPasswordFormFactory;
use App\UI\Portal\Components\Forms\SignIn\SignInForm;
use App\UI\Portal\Components\Forms\SignIn\SignInFormFactory;
use App\UI\Portal\Presenters\BasePresenter;
use App\UI\Portal\Presenters\CompanyNotRequiredInterface;
use DateTimeImmutable;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Security\AuthenticationException;
use ReflectionException;

class SignPresenterTrait
{
	#[Autowire]
	protected CompanyQueryFactory $companyQueryFactory;

	#[Autowire]
	protected Authenticator $authenticator;

	#[Autowire]
	protected OnetimeTokenQueryFactory $onetimeTokenQueryFactory;

	#[Autowire]
	protected EntityManager $em;

	protected Company|null $company = null;

	public function startup(): void
	{
		parent::startup();

		if ($this->isLogged() && !in_array($this->getAction(), ['out', 'token', 'newPassword'])) {
			if ($selectedCompany = $this->getUser()->getIdentity()->getFilteredCompany()?->getId()) {
				$this->presenter->redirect('Home:default', ['do' => 'redrawBody', 'selectedCompany' => $selectedCompany]);
			} else {
				$this->presenter->redirect('Dashboard:default', ['do' => 'redrawBody']);
			}
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
			$this->getUser()->login($email, $token, AclResourceEnum::CUSTOMER_DASHBOARD);
		} catch (AuthenticationException $e) {
			bd($e);
			$this->flashMessageError($e->getMessage());
			$this->getPresenter()->redirect(':Portal:Sign:in');
		}

		if (!$skipPasswordRecovery) {
			$this->redirect(':Portal:Sign:newPassword');
		} else {
			$this->onetimeTokenQueryFactory->create()->byIsValid()->byToken($token)->byType(OnetimeToken::TYPE_LOGIN)->fetchOne()->setUsedAt(new DateTimeImmutable());
			$this->em->flush();
		}
		$this->redirect('Home:');
	}

	public function actionNewPassword(): void
	{
		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect('in');
		}
	}

	public function createComponentSignInForm(SignInForm $factory): SignInForm
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

	public function actionLostPassword(): void
	{
	}
}
