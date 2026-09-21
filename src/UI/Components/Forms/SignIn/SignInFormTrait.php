<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\SignIn;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\PasskeyServiceInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\Components\Forms\PasskeyLoginTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;
use Nette\Security\AuthenticationException;

trait SignInFormTrait
{
	use ControlTrait;
	use RedirectAfterLoginTrait;
	use PasskeyLoginTrait;
	use FancyAdminInject;
	use AuthenticatorInject;
	use SecurityUserInject;
	use IdentityQueryFactoryInject;
	use PasskeyServiceInject;
	use TranslatorInject;

	private Identity $_identity;

	public function initForm(Form $form): void
	{
		$form->getElementPrototype()->class[] = 'login-form';

		$form->addSection(function () use ($form) {
			$form->addEmail('email')
				->setHtmlAttribute('id', 'login-form-input-email')
				->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.email')
				->setHtmlAttribute('autocomplete', 'username')
				->setRequired('fcadmin.forms.signIn.errors.emailRequired');

			// renderValue: false - heslo se tu jen zadava, do HTML nema co vypisovat
			$form->addPasswordReveal('password', false)
				->setHtmlAttribute('id', 'login-form-input-password')
				->setHtmlAttribute('placeholder', 'fcadmin.forms.signIn.labels.password')
				->setRequired('fcadmin.forms.signIn.errors.passwordRequired');
		}, 'inputsWrap');

		$form->addSection(name: 'lostPassword');

		$form->addSubmit('submit', 'fcadmin.forms.signIn.labels.logIn')
			->getControlPrototype()->class[] = 'w-100';

		if ($this->_fancyAdmin->isPasskeyEnabled()) {
			$form->addSection(name: 'passkey');
		}

		$this->getTemplate()->isLostPasswordEnabled = $this->_fancyAdmin->isLostPasswordEnabled();
		$this->getTemplate()->isPasskeyEnabled = $this->_fancyAdmin->isPasskeyEnabled();

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
		$this->getPresenter()->sendJson(['loginUrl' => $this->getKeycloakLoginUrl($email)]);
	}

	public function validateForm(array $values, Form $form): void
	{
		// Fallback pro klienty bez JS: AJAX kontrola (checkKeycloak) neproběhla,
		// takže SSO uživatele přesměrujeme na Keycloak login až při odeslání formuláře.
		// Heslo se v tom případě ignoruje - autorita pro SSO uživatele je Keycloak.
		// Při deaktivované instanci getKeycloakLoginUrl() vrátí null a uživatel se přihlašuje
		// heslem jako každý jiný (viz KeycloakManager::getInstanceForIdentity).
		if ($loginUrl = $this->getKeycloakLoginUrl($values['email'])) {
			$this->getPresenter()->redirectUrl($loginUrl);
		}

		try {
			$this->_identity = $this->_authenticator->authenticate($values['email'], $values['password'], $this->_fancyAdmin->getContext());

			if (
				!$this->_identity->isAllowed($this->_fancyAdmin->getCustomerAclResource())
				&&
				!$this->_identity->isAllowed($this->_fancyAdmin->getBackofficeAclResource())
			) {
				$form->addError('fcadmin.appGeneral.exceptions.noPermission');
				return;
			}

			// Až za authenticate(), aby se hláška nedala použít na enumeraci účtů
			if ($this->_passkeyService->isPasskeyRequired($this->_identity)) {
				// Samotné heslo nestačí — druhý krok nabídne klíč i jednorázový kód (README 19.9)
				if ($this->_fancyAdmin->isPasskeyEmailOtpEnabled()) {
					$this->_passkeyService->startPendingTwoFactor($this->_identity);
					$this->getPresenter()->redirect(':Portal:Sign:twoFactor');
				}

				// Bez záchranné cesty je heslo mrtvé jen pro identitu, která klíč má; bez klíče
				// by se k jeho registraci nedostala (bootstrap okno, README 19.8)
				if ($this->_passkeyService->hasPasskeys($this->_identity)) {
					$form->addError('fcadmin.passkeys.errors.passwordDisabled');
				}
			}
		} catch (AuthenticationException) {
			$form->addError($this->getRejectedSignInError($values['email']), false);
		}
	}

	/**
	 * Proc ne proste "neplatne prihlasovaci udaje": TooManyLoginAttemptsException z te hlasky
	 * dedi, takze uzivatel po vycerpani pokusu cetl tutez vetu jako pri prvnim prekleplem
	 * hesle. Nevedel, ze je zablokovany, ani do kdy, a sel s tim na podporu.
	 *
	 * Cisla si nepocitame sami, ale ptame se na ne autentizatoru - jinak by se rozesla s tim,
	 * co opravdu vynucuje, a hlaska by slibovala pokus, ktery uz neexistuje.
	 *
	 * Enumerace uctu tim nevznika: brzda pocita zadany retezec, at uz k nemu ucet existuje
	 * nebo ne, takze neexistujici e-mail odpovida uplne stejne.
	 */
	private function getRejectedSignInError(string $username): string
	{
		$status = $this->_authenticator->getLoginThrottleStatus($username);

		if ($status->remainingAttempts === null) {
			return $this->_translator->translate('fcadmin.appGeneral.exceptions.wrongCredentials');
		}

		if ($status->isBlocked()) {
			// Cas odblokovani knihovna nemusi znat - blokace na nem zamerne nevisi, aby se
			// brzda neotevrela kvuli tomu, ze se ho nepodarilo dopocitat.
			return $status->blockedUntil
				? $this->_translator->translate('fcadmin.appGeneral.exceptions.signInBlocked', [
					'time' => $status->blockedUntil->format('H:i'),
				])
				: $this->_translator->translate('fcadmin.appGeneral.exceptions.signInBlockedUnknownTime');
		}

		return $this->_translator->translate('fcadmin.appGeneral.exceptions.wrongCredentialsAttemptsLeft', [
			'count' => $status->remainingAttempts,
		]);
	}

	/**
	 * @throws AuthenticationException
	 */
	public function processForm(): never
	{
		$this->_securityUser->login($this->_identity, context: $this->_fancyAdmin->getContext());

		$this->_passkeyService->clearPasskeySession();
		$this->_passkeyService->clearTwoFactorSession();

		$this->redirectAfterLogin();
	}

	public function getEntityClass(): ?string
	{
		return null;
	}
}
