<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\SignIn;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;
use Nette\Security\AuthenticationException;

trait SignInFormTrait
{
	use ControlTrait;
	use RedirectAfterLoginTrait;
	use FancyAdminInject;
	use AuthenticatorInject;
	use SecurityUserInject;
	use IdentityQueryFactoryInject;

	private Identity $_identity;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addSection(function () use ($form) {
			$form->addEmail('email')
				->setHtmlAttribute('id', 'login-form-input-email')
				->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.email')
				->setRequired('fcadmin.forms.signIn.errors.emailRequired');

			$form->addPassword('password')
				->setHtmlAttribute('id', 'login-form-input-password')
				->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.password')
				->setRequired('fcadmin.forms.signIn.errors.passwordRequired');
		}, 'inputsWrap');

		$form->addSection(name: 'lostPassword');

		$form->addSubmit('submit', 'fcadmin.forms.signIn.labels.logIn')
			->getControlPrototype()->class[] = 'w-100';

		$this->getTemplate()->isLostPasswordEnabled = $this->_fancyAdmin->isLostPasswordEnabled();

		// Keycloak email check — přidá data atribut pro JS kontrolu
		if ($this->_fancyAdmin->isKeycloakEnabled()) {
			$form->getElementPrototype()->setAttribute('data-adt-sign-in-form', true);

			$form['email']->setHtmlAttribute(
				'data-keycloak-check-url',
				$this->link('checkKeycloak!', ['email' => '__EMAIL__'])
			);
		}
	}

	/**
	 * AJAX signal — ověří, zda se uživatel má přihlašovat přes SSO.
	 * Najde identitu podle emailu, zjistí přiřazenou SSO instanci,
	 * a pokud existuje, vrátí loginUrl s login_hint pro redirect.
	 */
	public function handleCheckKeycloak(string $email): void
	{
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			$this->getPresenter()->sendJson(['loginUrl' => null]);
			return;
		}

		if (empty(trim($email))) {
			$this->getPresenter()->sendJson(['loginUrl' => null]);
			return;
		}

		$manager = $this->_fancyAdmin->getKeycloakManager();
		if ($manager === null) {
			$this->getPresenter()->sendJson(['loginUrl' => null]);
			return;
		}

		// Najdeme identitu podle emailu a zjistíme přiřazenou SSO instanci
		$identity = $this->_identityQueryFactory->create()
			->byEmail($email)
			->fetchOneOrNull();

		if ($identity !== null) {
			$keycloak = $manager->getInstanceForIdentity($identity);
			if ($keycloak !== null) {
				$backRedirect = $this->getPresenter()->link(':Portal:Sign:in');
				$this->getPresenter()->sendJson([
					'loginUrl' => $keycloak->getLoginUrl($backRedirect, $email, true),
				]);
				return;
			}
		}

		$this->getPresenter()->sendJson(['loginUrl' => null]);
	}

	public function validateForm(array $values, Form $form): void
	{
		try {
			$this->_identity = $this->_authenticator->authenticate($values['email'], $values['password'], $this->_fancyAdmin->getContext());
		} catch (AuthenticationException) {
			$form->addError('fcadmin.appGeneral.exceptions.wrongCredentials');
		}
	}

	/**
	 * @throws AuthenticationException
	 */
	public function processForm(): never
	{
		$this->_securityUser->login($this->_identity, context: $this->_fancyAdmin->getContext());

		$this->redirectAfterLogin();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
