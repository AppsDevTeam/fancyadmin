<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Account;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\ChangePasswordFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\PasskeyServiceInject;
use ADT\FancyAdmin\DI\Injects\PersonalDataFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\DI\Injects\TranslatorInject;
use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyException;
use ADT\FancyAdmin\Model\Security\Passkey\PasskeyService;
use ADT\FancyAdmin\UI\Components\Forms\Passkey\PasskeyFormFactory;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControl;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelControlFactory;
use ADT\FancyAdmin\UI\Components\Grids\Passkey\PasskeyGrid;
use ADT\FancyAdmin\UI\Components\Grids\Passkey\PasskeyGridFactory;
use ADT\FancyAdmin\UI\Components\Grids\Session\SessionGrid;
use ADT\FancyAdmin\UI\Components\Grids\Session\SessionGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use RuntimeException;

trait AccountPresenterTrait
{
	use PresenterTrait;
	use SecurityUserInject;
	use AuthenticatorInject;
	use PersonalDataFormFactoryInject;
	use ChangePasswordFormFactoryInject;
	use FancyAdminInject;
	use PasskeyServiceInject;
	use TranslatorInject;

	public function actionDefault(): void
	{
		$identity = $this->_securityUser->getIdentity();
		$keycloak = $this->getKeycloakForIdentity();

		// Návrat z Keycloaku po dokončení Application-Initiated Action (změna hesla,
		// registrace nebo odebrání WebAuthn klíče). Výsledek se čte jednorázově ze session,
		// ne z URL — jinak by šlo podvrženým odkazem zobrazit cizí bezpečnostní hlášku.
		$actionResult = $keycloak?->consumeActionResult();
		if ($actionResult !== null) {
			$this->flashKeycloakActionResult($actionResult['action'], $actionResult['status']);
		}

		// Druhý faktor SSO uživatele spravuje Keycloak — aplikace jen zobrazí, co v něm je.
		// null znamená, že se stav nepodařilo zjistit (nedostupné Admin API) — pak nesmíme
		// tvrdit, že uživatel žádný klíč nemá.
		$credentials = $keycloak?->getWebAuthnCredentials($identity);

		$this->getTemplate()->identity = $identity;
		$this->getTemplate()->isPasskeyEnabled = $this->_fancyAdmin->isPasskeyEnabled();
		$this->getTemplate()->isKeycloak2faAvailable = $keycloak !== null;
		$this->getTemplate()->isKeycloak2faStateKnown = $credentials !== null;
		$this->getTemplate()->keycloak2faCredentials = $credentials ?? [];
		$this->getTemplate()->setFile(__DIR__ . '/default.latte');
	}

	/**
	 * Zobrazí hlášku podle výsledku Application-Initiated Action v Keycloaku.
	 * Stav `cancelled` (uživatel akci sám zrušil) hlášku nezobrazuje.
	 */
	private function flashKeycloakActionResult(string $kcAction, string $kcActionStatus): void
	{
		if ($kcActionStatus === 'success') {
			$this->flashMessageSuccess(match ($kcAction) {
				Keycloak::ACTION_UPDATE_PASSWORD => 'fcadmin.presenters.account.passwordChanged',
				Keycloak::ACTION_REGISTER_WEBAUTHN => 'fcadmin.twoFactor.messages.keyAdded',
				Keycloak::ACTION_DELETE_CREDENTIAL => 'fcadmin.twoFactor.messages.keyRemoved',
				default => 'fcadmin.twoFactor.messages.actionCompleted',
			});

			return;
		}

		if ($kcActionStatus === 'error') {
			$this->flashMessageError('fcadmin.twoFactor.errors.actionFailed');
		}
	}

	/**
	 * Vrátí Keycloak instanci přihlášené identity, nebo null, když identita není SSO
	 * (nebo je Keycloak vypnutý).
	 */
	private function getKeycloakForIdentity(): ?Keycloak
	{
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			return null;
		}

		return $this->_fancyAdmin->getKeycloakManager()
			?->getInstanceForIdentity($this->_securityUser->getIdentity());
	}

	public function handleEditPersonalData(): void
	{
		$this->redrawSidePanel('personalData');
	}

	public function handleChangePassword(): void
	{
		// SSO (Keycloak) uživatel si heslo spravuje v Keycloaku - místo formuláře pro změnu
		// lokálního hesla ho přesměrujeme na Keycloak (kc_action=UPDATE_PASSWORD), kde si
		// heslo změní rovnou (včetně ověření současného hesla) a vrátí se zpět na tuto stránku.
		$keycloak = $this->getKeycloakForIdentity();
		if ($keycloak !== null) {
			$this->getPresenter()->redirectUrl(
				$keycloak->getUpdatePasswordUrl(
					$this->getPresenter()->link('//default'),
					$this->_securityUser->getIdentity()->getEmail(),
				)
			);
		}

		$this->redrawSidePanel('changePassword');
	}

	/**
	 * Zapnutí druhého faktoru / přidání dalšího WebAuthn klíče.
	 * Registrace probíhá celá v Keycloaku (kc_action=webauthn-register) — aplikace klíč nevidí.
	 */
	public function handleAddTwoFactorKey(): void
	{
		$keycloak = $this->getKeycloakForIdentity();
		if ($keycloak === null) {
			$this->flashMessageError('fcadmin.twoFactor.errors.unavailable');
			$this->getPresenter()->redirect('default');
		}

		$this->getPresenter()->redirectUrl(
			$keycloak->getRegisterWebAuthnUrl(
				$this->getPresenter()->link('//default'),
				$this->_securityUser->getIdentity()->getEmail(),
			)
		);
	}

	/**
	 * Odebrání WebAuthn klíče. Keycloak zobrazí potvrzovací obrazovku a sám ověří,
	 * že klíč patří přihlášenému uživateli a že má na odebrání dostatečnou úroveň
	 * autentizace — aplikace klíč nemaže přes Admin API.
	 */
	public function handleDeleteTwoFactorKey(?string $credentialId = null): void
	{
		$keycloak = $this->getKeycloakForIdentity();
		if ($keycloak === null) {
			$this->flashMessageError('fcadmin.twoFactor.errors.unavailable');
			$this->getPresenter()->redirect('default');
		}

		if ($credentialId === null) {
			$this->flashMessageError('fcadmin.twoFactor.errors.unknownKey');
			$this->getPresenter()->redirect('default');
		}

		// Obrana do hloubky: ověříme vlastnictví klíče ještě před redirectem, ať uživateli
		// neposíláme cizí ID do Keycloaku (ten ho odmítne, ale zbytečně přes potvrzovací obrazovku)
		$credentials = $keycloak->getWebAuthnCredentials($this->_securityUser->getIdentity());
		if ($credentials === null) {
			$this->flashMessageError('fcadmin.twoFactor.errors.stateUnknown');
			$this->getPresenter()->redirect('default');
		}

		$isOwnCredential = false;
		foreach ($credentials as $credential) {
			if ($credential->getId() === $credentialId) {
				$isOwnCredential = true;
				break;
			}
		}

		if (!$isOwnCredential) {
			$this->flashMessageError('fcadmin.twoFactor.errors.unknownKey');
			$this->getPresenter()->redirect('default');
		}

		$this->getPresenter()->redirectUrl(
			$keycloak->getDeleteCredentialUrl(
				$credentialId,
				$this->getPresenter()->link('//default'),
				$this->_securityUser->getIdentity()->getEmail(),
			)
		);
	}

	public function handleLogoutAll(): never
	{
		$this->_authenticator->clearIdentity(
			$this->_securityUser->getIdentity()->getAuthObjectId()
		);
		$this->getUser()->logout(true);
		$this->redirect(':Portal:Sign:in');
	}

	public function createComponentPersonalDataSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		return $factory->create()
			->setFormFactory(fn() => $this->_personalDataFormFactory->create()
				->setEntity($this->_securityUser->getIdentity()));
	}

	public function createComponentChangePasswordSidePanel(SidePanelControlFactory $factory): SidePanelControl
	{
		return $factory->create()
			->setFormFactory(fn() => $this->_changePasswordFormFactory->create()
				->setEntity($this->_securityUser->getIdentity()));
	}

	public function createComponentSessionGrid(SessionGridFactory $factory): SessionGrid
	{
		return $factory->create();
	}

	// Passkey factories jsou nullable — projekt bez passkey tříd je nemá zaregistrované
	// a kdyby/autowired validuje parametry všech createComponent* metod už při attachi presenteru
	public function createComponentPasskeyGrid(?PasskeyGridFactory $factory = null): PasskeyGrid
	{
		if ($factory === null) {
			throw new RuntimeException('V projektu chybí implementace ' . PasskeyGridFactory::class . ' — vytvořte passkey třídy podle README (sekce 19).');
		}

		return $factory->create();
	}

	public function handleAddPasskey(): void
	{
		// Vypnutá featura nebo SSO uživatel — panel se ani neotevře
		try {
			$this->_passkeyService->assertEnabled();
			$this->_passkeyService->assertNotSso($this->_securityUser->getIdentity());
		} catch (PasskeyException $e) {
			$this->flashMessageError($e->getMessage());
			$this->getPresenter()->redirect('this');
		}

		$this->redrawSidePanel('addPasskey');
	}

	/**
	 * AJAX signal — vrátí PublicKeyCredentialCreationOptions pro registraci nového
	 * klíče přihlášené identity (binárky base64url, challenge one-shot v session).
	 */
	public function handlePasskeyRegisterArgs(): void
	{
		try {
			$args = $this->_passkeyService->getRegistrationArgs($this->_securityUser->getIdentity());
		} catch (PasskeyException $e) {
			$this->getPresenter()->sendJson(['error' => $e->getMessage()]);
		}

		$this->getPresenter()->sendJson($args);
	}

	/**
	 * AJAX signal — ověří odpověď autentikátoru (JSON tělo: {name, credential})
	 * a uloží nový klíč. Vrací {redirect} pro reload stránky, nebo {error}.
	 */
	public function handlePasskeyRegisterVerify(): void
	{
		try {
			$data = Json::decode((string) $this->getPresenter()->getHttpRequest()->getRawBody(), true);
		} catch (JsonException) {
			$data = null;
		}

		$response = is_array($data) ? ($data['credential']['response'] ?? null) : null;
		$clientDataJSON = is_array($response) ? PasskeyService::base64UrlDecode($response['clientDataJSON'] ?? null) : null;
		$attestationObject = is_array($response) ? PasskeyService::base64UrlDecode($response['attestationObject'] ?? null) : null;
		$transports = is_array($response) && is_array($response['transports'] ?? null) ? $response['transports'] : null;

		try {
			if ($clientDataJSON === null || $attestationObject === null) {
				throw new PasskeyException($this->_translator->translate('fcadmin.passkeys.errors.invalidKey'));
			}

			$this->_passkeyService->processRegistration(
				$this->_securityUser->getIdentity(),
				$clientDataJSON,
				$attestationObject,
				is_string($data['name'] ?? null) ? $data['name'] : '',
				$transports,
			);
		} catch (PasskeyException $e) {
			$this->getPresenter()->sendJson(['error' => $e->getMessage()]);
		}

		$this->flashMessageSuccess('fcadmin.passkeys.messages.added');
		$this->getPresenter()->redirect('this');
	}

	public function createComponentAddPasskeySidePanel(SidePanelControlFactory $factory, ?PasskeyFormFactory $passkeyFormFactory = null): SidePanelControl
	{
		if ($passkeyFormFactory === null) {
			throw new RuntimeException('V projektu chybí implementace ' . PasskeyFormFactory::class . ' — vytvořte passkey třídy podle README (sekce 19).');
		}

		return $factory->create()
			->setFormFactory(fn() => $passkeyFormFactory->create());
	}
}
