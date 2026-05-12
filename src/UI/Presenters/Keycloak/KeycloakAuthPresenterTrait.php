<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Keycloak;

use ADT\FancyAdmin\DI\Injects\AuthenticatorInject;
use ADT\FancyAdmin\DI\Injects\FancyAdminInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\Model\Security\Keycloak\KeycloakSessionSection;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use Nette\Application\Attributes\CrossOrigin;
use Nette\Application\Responses\TextResponse;
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
	 */
	#[CrossOrigin]
	public function actionCallback(?string $state = null, ?string $code = null, ?string $error = null, ?string $instance = null): void
	{
		if ($error !== null || $code === null || $instance === null) {
			$this->redirect(':Portal:Sign:in');
			return;
		}

		$this->processKeycloakAuthRequest($code, $instance, $state);
	}

	/**
	 * Silent SSO check — Keycloak přesměruje sem po prompt=none.
	 */
	public function actionSilentCheck(?string $code = null, ?string $error = null, ?string $backRedirect = null, ?string $instance = null): void
	{
		if ($error !== null || $code === null || $instance === null) {
			if ($backRedirect !== null && Validators::isUrl($backRedirect)) {
				$this->redirectUrl($backRedirect);
			}
			$this->redirect(':Portal:Sign:in');
			return;
		}

		$this->processKeycloakAuthRequest($code, $instance, $backRedirect);
	}

	/**
	 * Po přihlášení přes Keycloak — silent check pro ověření, že session funguje.
	 */
	public function actionAfterLoginSilentCheck(?string $code = null, ?string $error = null, ?string $backRedirect = null, ?string $instance = null): void
	{
		$keycloakSession = $this->getSession(KeycloakSessionSection::SECTION_NAME);
		$afterLoginSilentCheck = $keycloakSession->get(KeycloakSessionSection::AFTER_LOGIN_SILENT_CHECK);
		$keycloakSession->remove(KeycloakSessionSection::AFTER_LOGIN_SILENT_CHECK);

		if ($afterLoginSilentCheck && $error === null && $code !== null && $instance !== null) {
			$this->processKeycloakAuthRequest($code, $instance, $backRedirect);
			return;
		}

		if ($backRedirect !== null && Validators::isUrl($backRedirect)) {
			$this->redirectUrl($backRedirect);
		}

		$this->redirect(':Portal:Sign:in');
	}

	/**
	 * Keycloak post-logout redirect — sem Keycloak přesměruje po úspěšném logoutu.
	 */
	public function actionPostLogOut(?string $state = null): void
	{
		if ($state !== null && Validators::isUrl($state)) {
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

		$claims = $keycloak->decodeLogoutToken($logoutToken);
		$sub = $claims['sub'] ?? null;

		if ($sub === null) {
			$this->getHttpResponse()->setCode(400);
			$this->sendResponse(new TextResponse('Missing sub claim'));
		}

		// Najdeme uživatele v Keycloaku podle sub → získáme email
		$keycloakUser = $keycloak->getUserById($sub);
		if ($keycloakUser === null || $keycloakUser->getEmail() === null) {
			$this->getHttpResponse()->setCode(200);
			$this->sendResponse(new TextResponse('OK'));
		}

		// Najdeme lokální identitu podle emailu a invalidujeme všechny její sessions
		$identity = $this->_identityQueryFactory->create()
			->disableSecurityFilter()
			->byEmail($keycloakUser->getEmail())
			->fetchOneOrNull();

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

	private function processKeycloakAuthRequest(string $code, string $instanceName, ?string $backRedirect = null): void
	{
		$manager = $this->_fancyAdmin->getKeycloakManager();
		$keycloak = $manager?->getInstance($instanceName);

		if ($keycloak === null) {
			$this->redirect(':Portal:Sign:in');
			return;
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

		$keycloakAuthentication = $keycloak->checkSessionValidity($code);

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

		// Reset auth attempt counter
		$keycloakSession->set(KeycloakSessionSection::AUTH_ATTEMPT_COUNT, 0);

		// Po přihlášení zkontrolujeme silent check
		$keycloakSession->set(KeycloakSessionSection::AFTER_LOGIN_SILENT_CHECK, true);
		$silentCheckUrl = $keycloak->getSilentLoginUrl($backRedirect, 'afterLoginSilentCheck');
		$this->redirectUrl($silentCheckUrl);
	}

	public function formatTemplateFiles(): array
	{
		$list = parent::formatTemplateFiles();
		$list[] = __DIR__ . '/' . $this->view . '.latte';
		return $list;
	}
}
