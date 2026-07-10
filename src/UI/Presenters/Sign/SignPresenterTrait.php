<?php

namespace ADT\FancyAdmin\UI\Presenters\Sign;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\LostPassword\LostPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordForm;
use ADT\FancyAdmin\UI\Components\Forms\NewPassword\NewPasswordFormFactory;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInForm;
use ADT\FancyAdmin\UI\Components\Forms\SignIn\SignInFormFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\RedirectAfterLoginTrait;
use Nette\Application\Attributes\Persistent;
use Nette\Security\AuthenticationException;
use Nette\Utils\Validators;

trait SignPresenterTrait
{
	use PresenterTrait;
	use RedirectAfterLoginTrait;
	use EntityManagerInject;
	use FancyAdminInject;
	use SecurityUserInject;
	use AuthenticatorInject;

	#[Persistent]
	public ?string $token = null;

	protected Identity $identity;

	public function actionIn(?string $errorMsg): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->redirectAfterLogin();
		}

		// Automatický pokus o SSO přihlášení (silent check, prompt=none).
		// Pokud má uživatel aktivní session v Keycloaku a v aplikaci existuje
		// odpovídající identita, přihlásí se rovnou bez zadávání údajů.
		$this->attemptSilentSso();

		if ($errorMsg) {
			$this->flashMessageError($errorMsg);
		}

		if ($this->getParameter('fraudDetected')) {
			$this->flashMessageError('_fcadmin.modules.web.presenters.sign.flashFraud');
		}
	}

	/**
	 * Zkusí nepřihlášeného uživatele automaticky přihlásit přes existující Keycloak SSO session.
	 *
	 * Pro každou SSO instanci provede jeden silent check (prompt=none). Keycloak buď vrátí
	 * authorization code (existuje aktivní SSO session) a uživatel se přihlásí v callbacku,
	 * nebo vrátí chybu (login_required) a uživatel se vrátí sem na formulář.
	 *
	 * Každá instance se v rámci jedné session zkusí maximálně jednou (kvůli ochraně před smyčkou).
	 */
	private function attemptSilentSso(): void
	{
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			return;
		}

		// Signálové požadavky (např. AJAX kontrola emailu signInForm-checkKeycloak) nesmí
		// spouštět silent SSO — fetch by následoval cross-origin redirect na Keycloak
		// a spadl by na CORS chybu místo doručení odpovědi signálu.
		if ($this->getSignal() !== null) {
			return;
		}

		$manager = $this->_fancyAdmin->getKeycloakManager();
		if ($manager === null || !$manager->hasInstances()) {
			return;
		}

		$keycloakSession = $this->getSession(KeycloakSessionSection::SECTION_NAME);

		// Po explicitním odhlášení přeskočíme jeden silent SSO pokus,
		// jinak by uživatele mohlo hned znovu přihlásit a nešlo by se odhlásit.
		if ($keycloakSession->get(KeycloakSessionSection::SSO_SUPPRESS_SILENT)) {
			$keycloakSession->remove(KeycloakSessionSection::SSO_SUPPRESS_SILENT);
			return;
		}

		$tried = $keycloakSession->get(KeycloakSessionSection::SSO_SILENT_TRIED) ?? [];

		foreach ($manager->getInstanceNames() as $name) {
			if (in_array($name, $tried, true)) {
				continue;
			}

			$keycloak = $manager->getInstance($name);
			if ($keycloak === null) {
				continue;
			}

			// Pojistka proti chybné konfiguraci — bez validní absolutní hostUrl
			// by se vygenerovala rozbitá relativní redirect URL.
			if (!Validators::isUrl($keycloak->getHostUrl())) {
				continue;
			}

			$tried[] = $name;
			$keycloakSession->set(KeycloakSessionSection::SSO_SILENT_TRIED, $tried);

			// Po úspěšném (i neúspěšném) silent checku se vrátíme na tuto přihlašovací stránku
			// (včetně případného backlinku), odkud se buď pokračuje na další instanci, nebo se zobrazí formulář.
			$backRedirect = $this->getHttpRequest()->getUrl()->getAbsoluteUrl();
			$this->redirectUrl($keycloak->getSilentLoginUrl($backRedirect));
		}
	}

	/**
	 * Potlačí jeden následující automatický silent SSO pokus na přihlašovací stránce.
	 * Voláme při explicitním odhlášení, aby uživatele po logoutu hned znovu nepřihlásilo.
	 */
	private function suppressSilentSso(): void
	{
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			return;
		}

		$this->getSession(KeycloakSessionSection::SECTION_NAME)
			->set(KeycloakSessionSection::SSO_SUPPRESS_SILENT, true);
	}

	public function actionOut(): never
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->suppressSilentSso();

			// Pokud je Keycloak zapnutý a uživatel se přihlásil přes Keycloak, přesměrujeme na Keycloak logout
			if ($this->_fancyAdmin->isKeycloakEnabled()) {
				$keycloak = $this->_fancyAdmin->getKeycloakManager()?->getInstanceFromSession();
				if ($keycloak === null) {
					// Fallback — zkusíme najít instanci dle identity
					$keycloak = $this->_fancyAdmin->getKeycloakManager()?->getInstanceForIdentity($this->getUser()->getIdentity());
				}
				$redirectUrl = $this->getPresenter()->link(':Portal:Sign:in');
				$logoutUrl = $keycloak?->getLogoutUrl($redirectUrl);

				if ($logoutUrl !== null) {
					$this->getUser()->logout(true);
					$this->redirectUrl($logoutUrl);
				}
			}

			$this->getUser()->logout(true);
		}

		$this->redirect('in');
	}

	public function actionOutAll(): never
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->suppressSilentSso();

			$this->_authenticator->clearIdentity(
				$this->getUser()->getIdentity()->getAuthObjectId()
			);
			$this->getUser()->logout(true);
		}

		$this->redirect('in');
	}

	public function actionNewPassword(string $token): void
	{
		$this->user->logout(clearIdentity: true);

		try {
			$this->identity = $this->_authenticator->authenticate($token);
		} catch(AuthenticationException) {
			$this->flashMessageError('fcadmin.presenters.sign.errors.expiredLink');
			$this->redirect(':Portal:Sign:lostPassword');
		}
	}

	public function actionPasswordSet(): void
	{
		$this->template->canContinue = $this->getUser()->isLoggedIn();
	}

	public function handleContinue(): void
	{
		$this->redirectAfterLogin();
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

	public function createComponentNewPasswordForm(NewPasswordFormFactory $factory): NewPasswordForm
	{
		return $factory->create()->setEntity($this->identity);
	}
}
