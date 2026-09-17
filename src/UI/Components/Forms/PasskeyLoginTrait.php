<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms;

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
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Přihlášení přihlašovacím klíčem (WebAuthn) sdílené přihlašovacím formulářem
 * a druhým krokem při vynuceném 2FA (README 19.9).
 */
trait PasskeyLoginTrait
{
	use ControlTrait;
	use RedirectAfterLoginTrait;
	use FancyAdminInject;
	use SecurityUserInject;
	use IdentityQueryFactoryInject;
	use PasskeyServiceInject;
	use TranslatorInject;

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

			// Uživatel, který se přihlašuje přes Keycloak (SSO instance + role s needsSso),
			// se přes passkey nepřihlásí: Keycloak login má přednost, přesměrujeme ho na něj
			// natvrdo. Klíč si ale zaregistrovat může, aby měl 2FA připravené na dobu,
			// kdy mu SSO bude zrušeno.
			if (($keycloakLoginUrl = $this->getKeycloakLoginUrl((string) $identity->getEmail())) !== null) {
				$this->getPresenter()->sendJson(['redirect' => $keycloakLoginUrl]);
			}

			// Stejný ACL check jako AuthenticatorTrait::validateIdentity()
			if (
				!$identity->isAllowed($this->_fancyAdmin->getCustomerAclResource())
				&&
				!$identity->isAllowed($this->_fancyAdmin->getBackofficeAclResource())
			) {
				throw new PasskeyException($this->_translator->translate('fcadmin.appGeneral.exceptions.noPermission'));
			}

			$this->_securityUser->login($identity, context: $this->_fancyAdmin->getContext());

			// U identity s vynuceným 2FA je passkey session jediná, kterou AuthPresenter
			// nechá naživu (README 19.8)
			$this->_passkeyService->markPasskeySession();
			$this->_passkeyService->clearTwoFactorSession();
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
		$identity = $this->findIdentityByEmail($email);
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

	private function findIdentityByEmail(string $email): ?Identity
	{
		return $this->_identityQueryFactory->create()
			->byEmail($email)
			->fetchOneOrNull();
	}
}
