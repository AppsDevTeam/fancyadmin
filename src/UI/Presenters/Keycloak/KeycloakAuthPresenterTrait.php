<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Keycloak;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\Model\Security\Keycloak\Keycloak;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use Nette\Application\Attributes\CrossOrigin;
use Nette\Application\Responses\TextResponse;
use Nette\Http\Url;
use Nette\Utils\Validators;

trait KeycloakAuthPresenterTrait
{
	use PresenterTrait;
	use FancyAdminInject;
	use AuthenticatorInject;
	use IdentityQueryFactoryInject;

	private const int MAX_AUTH_ATTEMPTS = 3;
	private const int AUTH_ATTEMPT_WINDOW_SECONDS = 120;

	protected function startup(): void
	{
		parent::startup();
		if (!$this->_fancyAdmin->isKeycloakEnabled()) {
			$this->error('Keycloak is not enabled', 404);
		}
	}

	/**
	 * Keycloak callback — zpracuje authorization code po redirectu z Keycloaku.
	 * Parametr `instance` identifikuje, ze které Keycloak instance callback přišel.
	 * `state` je CSRF token vydaný při startu flow — ověřuje se proti session,
	 * návratová URL se čte ze session (do Keycloaku se neposílá).
	 */
	#[CrossOrigin]
	public function actionCallback(?string $state = null, ?string $code = null, ?string $error = null, ?string $instance = null): void
	{
		if ($error !== null || $code === null || $instance === null) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		// Úspěšně dokončená Application-Initiated Action (např. změna hesla z Můj profil) —
		// propíše se do návratové URL, aby cílová stránka mohla zobrazit success hlášku.
		$kcActionSuccess = $this->getHttpRequest()->getQuery('kc_action_status') === 'success';

		$this->processKeycloakAuthRequest($code, $instance, $state, kcActionSuccess: $kcActionSuccess);
	}

	/**
	 * Silent SSO check — Keycloak přesměruje sem po prompt=none.
	 */
	public function actionSilentCheck(?string $state = null, ?string $code = null, ?string $error = null, ?string $instance = null): void
	{
		// Zkušební průchod z administrace (SSO grid) - jen ohlásíme, jak Keycloak odpověděl,
		// a nikoho nepřihlašujeme. Musí se to rozhodnout tady, protože obě větve níž už
		// vedou k autentizaci; kdyby test propadl do nich, admin by se přihlásil cizí
		// identitou nebo by se přes defaultRole provisionovala nová.
		$keycloak = $instance !== null ? $this->_fancyAdmin->getKeycloakManager()?->getInstance($instance) : null;
		if ($keycloak?->isTestAuthState($state)) {
			$this->finishSsoTest($keycloak, $state, $error, $code);
			return;
		}

		if ($error !== null || $code === null || $instance === null) {
			// Silent check neprošel (typicky error=login_required) — vrátíme se na URL,
			// ze které byl flow spuštěn. Čte se ze session přes state, ne z requestu.
			$backRedirect = ($keycloak?->consumeAuthState($state) ?? [])['backRedirect'] ?? null;

			if ($backRedirect !== null && Validators::isUrl($backRedirect)) {
				$this->redirectUrl($backRedirect);
			}
			$this->redirect(':Portal:Sign:in');
			return;
		}

		$this->processKeycloakAuthRequest($code, $instance, $state, isSilent: true);
	}

	/**
	 * Keycloak post-logout redirect — sem Keycloak přesměruje po úspěšném logoutu.
	 */
	public function actionPostLogOut(?string $state = null): void
	{
		// state smí mířit jen na tuto aplikaci — jinak by šel endpoint zneužít jako open redirect
		if ($state !== null && Validators::isUrl($state)
			&& (new Url($state))->getHost() === $this->getHttpRequest()->getUrl()->getHost()
		) {
			$this->redirectUrl($state);
		}

		$this->redirect(':Portal:Sign:in');
	}

	/**
	 * Backchannel logout — Keycloak pošle POST při ukončení session.
	 * Endpoint musí být veřejně dostupný (volá ho Keycloak server).
	 */
	#[CrossOrigin]
	public function actionBackchannelLogout(?string $instance = null): void
	{
		$logoutToken = $this->getHttpRequest()->getPost('logout_token');

		if ($logoutToken === null || $instance === null) {
			$this->getHttpResponse()->setCode(400);
			$this->sendResponse(new TextResponse('Missing logout_token or instance'));
		}

		$manager = $this->_fancyAdmin->getKeycloakManager();
		$keycloak = $manager?->getInstance($instance);

		if ($keycloak === null) {
			$this->getHttpResponse()->setCode(400);
			$this->sendResponse(new TextResponse('Unknown instance'));
		}

		// Plná validace podle OIDC Back-Channel Logout spec (podpis, iss, aud, events, replay).
		// Bez ní by šlo podvrženým POSTem odhlásit libovolného uživatele.
		$claims = $keycloak->validateLogoutToken($logoutToken);

		if ($claims === null) {
			$this->getHttpResponse()->setCode(400);
			$this->sendResponse(new TextResponse('Invalid logout token'));
		}

		$sub = $claims['sub'] ?? null;

		if ($sub === null) {
			$this->getHttpResponse()->setCode(400);
			$this->sendResponse(new TextResponse('Missing sub claim'));
		}

		// Identitu hledáme přímo podle `sub` - stejně jako při přihlášení. Dotaz do Keycloaku
		// na e-mail je až záloha pro identity, které `ssoSub` ještě nemají; těm se doplní
		// při nejbližším přihlášení (viz Keycloak::loginUser), takže tahle větev postupně
		// přestane být potřeba.
		$identity = $this->_identityQueryFactory->create()
			->disableSecurityFilter()
			->bySsoSub($sub)
			->fetchOneOrNull();

		if ($identity === null) {
			$keycloakUser = $keycloak->getUserById($sub);
			if ($keycloakUser === null || $keycloakUser->getEmail() === null) {
				$this->getHttpResponse()->setCode(200);
				$this->sendResponse(new TextResponse('OK'));
			}

			$identity = $this->_identityQueryFactory->create()
				->disableSecurityFilter()
				->byEmail($keycloakUser->getEmail())
				->fetchOneOrNull();
		}

		if ($identity !== null) {
			$this->_authenticator->clearIdentity($identity->getAuthObjectId());
		}

		$this->getHttpResponse()->setCode(200);
		$this->sendResponse(new TextResponse('OK'));
	}

	/**
	 * Stránka pro silent SSO iframe check (keycloak-js adapter).
	 */
	public function actionSilentCheckSso(): void
	{
		$this->setLayout(false);
	}

	/**
	 * Vyhodnotí zkušební průchod a vrátí admina zpět do administrace s výsledkem.
	 *
	 * Sem se dostane jen průchod, který Keycloak přesměroval zpět: hostUrl je dosažitelná,
	 * realm existuje a Keycloak client_id i redirect_uri přijal. Když Keycloak client_id
	 * nezná nebo redirect_uri nemá registrované, zpět nepřesměruje a admin zůstane na jeho
	 * chybové stránce - tenhle kód se pak nezavolá vůbec (viz README 18.2).
	 *
	 * error=login_required je také ÚSPĚCH: znamená, že Keycloak request přijal a zpracoval,
	 * jen zrovna neběží žádná SSO session. To je u admina, který v Keycloaku přihlášený
	 * není, ten očekávaný výsledek.
	 *
	 * Výsledek se předává v návratové URL parametrem `ssoTest`, ne flash zprávou: backRedirect
	 * vznikl v gridu před jakoukoli flash zprávou, takže nenese `_fid`, a redirectUrl() na
	 * absolutní URL ho nedoplní - flash by se na cílové stránce nenašla. Cílový presenter
	 * (SsoPresenterTrait::actionDefault) z parametru udělá flash a URL vyčistí.
	 */
	private function finishSsoTest(Keycloak $keycloak, ?string $state, ?string $error, ?string $code): void
	{
		$authState = $keycloak->consumeAuthState($state);

		$result = ($code !== null || in_array($error, ['login_required', 'interaction_required'], true))
			? Keycloak::SSO_TEST_OK
			: $this->sanitizeSsoTestError($error);

		$backRedirect = $authState['backRedirect'] ?? null;
		if ($backRedirect !== null && Validators::isUrl($backRedirect)) {
			$this->redirectUrl((string) (new Url($backRedirect))->setQueryParameter(Keycloak::SSO_TEST_PARAM, $result));
		}

		// Sem se to dostane jen při ztraceném/neplatném backRedirectu. Konfigurovaná route,
		// ne natvrdo :PortalBackoffice:Sso: - tu si aplikace může namapovat jinam.
		// Interní redirect `_fid` doplní, flash tady projde.
		if ($result === Keycloak::SSO_TEST_OK) {
			$this->flashMessageSuccess('fcadmin.presenters.sso.messages.testOk');
		} else {
			$this->flashMessageError('fcadmin.presenters.sso.errors.testFailed', parameters: ['error' => $result]);
		}
		$this->redirect($this->_fancyAdmin->getDefaultBackofficeRoute());
	}

	/**
	 * Kód chyby od Keycloaku (OAuth `error`, např. `unauthorized_client`, `invalid_scope`) se
	 * propisuje do URL a odtud do hlášky. Necháme jen bezpečnou podmnožinu znaků, ať se do
	 * hlášky nedostane nic, co Keycloak (nebo někdo za něj) do parametru vloží.
	 */
	private function sanitizeSsoTestError(?string $error): string
	{
		$error = $error !== null ? preg_replace('/[^a-z0-9_.-]/i', '', $error) : '';

		return $error !== '' && $error !== Keycloak::SSO_TEST_OK ? substr($error, 0, 64) : 'unknown';
	}

	private function processKeycloakAuthRequest(string $code, string $instanceName, ?string $state = null, bool $isSilent = false, bool $kcActionSuccess = false): void
	{
		$manager = $this->_fancyAdmin->getKeycloakManager();
		$keycloak = $manager?->getInstance($instanceName);

		if ($keycloak === null) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		// CSRF ochrana: state musí odpovídat autorizačnímu requestu rozpracovanému v této session.
		// Neznámý/expirovaný state = možný podvržený callback (code injection) — nepokračujeme.
		$authState = $keycloak->consumeAuthState($state);
		if ($authState === null) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		$backRedirect = $authState['backRedirect'];
		if ($kcActionSuccess && $backRedirect !== null) {
			$backRedirect = (string) (new Url($backRedirect))->setQueryParameter('kcActionSuccess', '1');
		}

		$keycloakSession = $this->getSession(KeycloakSessionSection::SECTION_NAME);

		// Loop detection — max 3 pokusy za 120 sekund
		$attemptCount = (int) $keycloakSession->get(KeycloakSessionSection::AUTH_ATTEMPT_COUNT);
		$attemptLastTime = $keycloakSession->get(KeycloakSessionSection::AUTH_ATTEMPT_LAST_TIME);

		if ($attemptLastTime !== null) {
			$elapsed = time() - (int) $attemptLastTime;
			if ($elapsed > self::AUTH_ATTEMPT_WINDOW_SECONDS) {
				$attemptCount = 0;
			}
		}

		$attemptCount++;
		$keycloakSession->set(KeycloakSessionSection::AUTH_ATTEMPT_COUNT, $attemptCount);
		$keycloakSession->set(KeycloakSessionSection::AUTH_ATTEMPT_LAST_TIME, time());

		if ($attemptCount > self::MAX_AUTH_ATTEMPTS) {
			$keycloakSession->set(KeycloakSessionSection::AUTH_ATTEMPT_COUNT, 0);
			$this->redirect(':Portal:Sign:in');
			return;
		}

		// redirect_uri musí být totožné s tím, které bylo použito v autorizačním requestu,
		// jinak Keycloak výměnu code za token odmítne (silent check používá jiné redirect_uri než callback).
		$redirectUri = $isSilent
			? $keycloak->getSilentRedirectUri()
			: $keycloak->getAuthRedirectUri();

		$keycloakAuthentication = $keycloak->checkSessionValidity($code, $redirectUri, $authState['verifier']);

		if ($keycloakAuthentication === null) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		$userInfo = $keycloakAuthentication->getUserInfo();
		$email = $userInfo['email'] ?? null;

		if (empty($email)) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		try {
			$keycloak->loginUser($keycloakAuthentication);
		} catch (\Nette\Security\AuthenticationException $e) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		// Uložíme název instance do session pro logout a frontend
		$manager->storeInstanceInSession($instanceName);

		// Reset auth attempt counteru a příznaku silent SSO pokusů
		$keycloakSession->set(KeycloakSessionSection::AUTH_ATTEMPT_COUNT, 0);
		$keycloakSession->remove(KeycloakSessionSection::SSO_SILENT_TRIED);

		// Přihlášení proběhlo úspěšně — přesměrujeme zpět tam, odkud uživatel přišel.
		if ($backRedirect !== null && Validators::isUrl($backRedirect)) {
			$this->redirectUrl($backRedirect);
		}

		$this->redirect(':Portal:Sign:in');
	}

	public function formatTemplateFiles(): array
	{
		$list = parent::formatTemplateFiles();
		$list[] = __DIR__ . '/' . $this->getView() . '.latte';
		return $list;
	}
}
