<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\Model\Entities\OnetimeToken;
use ADT\FancyAdmin\Model\Queries\Factories\OnetimeTokenQueryFactory;
use ADT\FancyAdmin\Model\Queries\OnetimeTokenQuery;
use ADT\FancyAdmin\UI\Forms\SignIn\LostPasswordForm;
use ADT\FancyAdmin\UI\Forms\SignIn\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Forms\SignIn\NewPasswordForm;
use ADT\FancyAdmin\UI\Forms\SignIn\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Forms\SignIn\SignInFormFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Kdyby\Autowired\Attributes\Autowire;
use Nette\Security\AuthenticationException;
use ReflectionException;

trait SignPresenterTrait
{
	use PresenterTrait;
	use RedirectAfterLoginTrait;

	private OnetimeTokenQueryFactory $_onetimeTokenQueryFactory;
	public function injectOnetimeTokenQueryFactory(OnetimeTokenQueryFactory $factory): void
	{
		$this->_onetimeTokenQueryFactory = $factory;
	}

	private EntityManagerInterface $_em;
	public function injectEntityManager(EntityManagerInterface $em): void
	{
		$this->_em = $em;
	}

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
			$this->getUser()->login($email, $token, AclResourceEnum::CUSTOMER_DASHBOARD);
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

	public function createComponentSignInForm(\ADT\FancyAdmin\UI\Forms\SignIn\SignInFormFactory $factory): SignInForm
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
