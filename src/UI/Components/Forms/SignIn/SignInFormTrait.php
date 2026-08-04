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
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyException;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\UI\Components\ControlTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use ADT\Forms\Form;
use Nette\Security\AuthenticationException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

trait SignInFormTrait
{
	use ControlTrait;
	use RedirectAfterLoginTrait;
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

			$form->addPassword('password')
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

	/**
	 * AJAX signal — vrátí PublicKeyCredentialRequestOptions pro usernameless
	 * passkey login (binárky base64url). Challenge se drží one-shot v session.
	 */
	public function handlePasskeyLoginArgs(): void
	{
		try {
			$args = $this->_passkeyService->getLoginArgs();
		} catch (PasskeyException $e) {
			$this->getPresenter()->sendJson(['error' => $e->getMessage()]);
		}

		$this->getPresenter()->sendJson($args);
	}

	/**
	 * AJAX signal — ověří WebAuthn assertion (JSON tělo requestu ve formátu
	 * PublicKeyCredential.toJSON()), přihlásí identitu a vrátí JSON s redirect URL
	 * (přes redirectAfterLogin(), který pod AJAXem pošle payload {redirect: ...}).
	 */
	public function handlePasskeyLoginVerify(): void
	{
		$credential = $this->parsePasskeyCredential();

		try {
			if ($credential === null) {
				throw new PasskeyException($this->_translator->translate('fcadmin.passkeys.errors.invalidKey'));
			}

			$identity = $this->_passkeyService->processLogin(
				$credential['credentialId'],
				$credential['clientDataJSON'],
				$credential['authenticatorData'],
				$credential['signature'],
				$credential['userHandle'],
			);

			// Stejný ACL check jako AuthenticatorTrait::validateIdentity()
			if (
				!$identity->isAllowed($this->_fancyAdmin->getCustomerAclResource())
				&&
				!$identity->isAllowed($this->_fancyAdmin->getBackofficeAclResource())
			) {
				throw new PasskeyException($this->_translator->translate('fcadmin.appGeneral.exceptions.noPermission'));
			}

			$this->_securityUser->login($identity, context: $this->_fancyAdmin->getContext());
		} catch (PasskeyException $e) {
			$this->getPresenter()->sendJson(['error' => $e->getMessage()]);
		}

		$this->redirectAfterLogin();
	}

	/**
	 * Načte a dekóduje WebAuthn assertion z JSON těla requestu
	 * (výstup PublicKeyCredential.toJSON(), binárky base64url).
	 *
	 * @return array{credentialId: string, clientDataJSON: string, authenticatorData: string, signature: string, userHandle: ?string}|null
	 */
	private function parsePasskeyCredential(): ?array
	{
		try {
			$data = Json::decode((string) $this->getPresenter()->getHttpRequest()->getRawBody(), true);
		} catch (JsonException) {
			return null;
		}

		if (!is_array($data)) {
			return null;
		}

		$response = $data['response'] ?? null;
		if (!is_array($response)) {
			return null;
		}

		$credentialId = PasskeyService::base64UrlDecode($data['rawId'] ?? $data['id'] ?? null);
		$clientDataJSON = PasskeyService::base64UrlDecode($response['clientDataJSON'] ?? null);
		$authenticatorData = PasskeyService::base64UrlDecode($response['authenticatorData'] ?? null);
		$signature = PasskeyService::base64UrlDecode($response['signature'] ?? null);

		if ($credentialId === null || $clientDataJSON === null || $authenticatorData === null || $signature === null) {
			return null;
		}

		return [
			'credentialId' => $credentialId,
			'clientDataJSON' => $clientDataJSON,
			'authenticatorData' => $authenticatorData,
			'signature' => $signature,
			'userHandle' => PasskeyService::base64UrlDecode($response['userHandle'] ?? null),
		];
	}

	/**
	 * Vrátí Keycloak login URL, pokud se má uživatel s daným emailem přihlašovat přes SSO.
	 * Jinak vrátí null (uživatel neexistuje, nemá SSO instanci nebo Keycloak není zapnutý).
	 */
	private function getKeycloakLoginUrl(string $email): ?string
	{
		if (!$this->_fancyAdmin->isKeycloakEnabled() || empty(trim($email))) {
			return null;
		}

		$manager = $this->_fancyAdmin->getKeycloakManager();
		if ($manager === null) {
			return null;
		}

		// Najdeme identitu podle emailu a zjistíme přiřazenou SSO instanci
		$identity = $this->_identityQueryFactory->create()
			->byEmail($email)
			->fetchOneOrNull();

		if ($identity === null) {
			return null;
		}

		$keycloak = $manager->getInstanceForIdentity($identity);
		if ($keycloak === null) {
			return null;
		}

		$backRedirect = $this->getPresenter()->link(':Portal:Sign:in');
		return $keycloak->getLoginUrl($backRedirect, $email, true);
	}

	public function validateForm(array $values, Form $form): void
	{
		// Fallback pro klienty bez JS: AJAX kontrola (checkKeycloak) neproběhla,
		// takže SSO uživatele přesměrujeme na Keycloak login až při odeslání formuláře.
		// Heslo se v tom případě ignoruje - autorita pro SSO uživatele je Keycloak.
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
			}

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
