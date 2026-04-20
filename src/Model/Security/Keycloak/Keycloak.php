<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Keycloak;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Sso;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\AclRoleQueryFactory;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\Model\Security\SecurityUser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Nette\Application\LinkGenerator;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Http\Session;
use Nette\Http\Url;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;

class Keycloak
{
	private string $realm;
	private string $baseUrl;
	private string $hostUrl;
	private string $clientId;
	private string $clientSecret;
	private string $frontendClientId;

	private ?Client $client = null;

	private LinkGenerator $linkGenerator;

	private SecurityUser $securityUser;

	private IdentityQueryFactory $identityQueryFactory;

	private AclRoleQueryFactory $aclRoleQueryFactory;

	private FancyAdmin $fancyAdmin;

	private Session $session;

	private Cache $cache;

	private ?KeycloakAdminAccessToken $adminAccessToken = null;

	private string $instanceName = 'default';

	private ?string $defaultRoleName;

	protected EntityManager $em;

	public function __construct(
		string $realm,
		string $baseUrl,
		string $hostUrl,
		string $clientId,
		string $clientSecret,
		string $frontendClientId,
		EntityManager $em,
		LinkGenerator $linkGenerator,
		SecurityUser $securityUser,
		IdentityQueryFactory $identityQueryFactory,
		AclRoleQueryFactory $aclRoleQueryFactory,
		FancyAdmin $fancyAdmin,
		Session $session,
		Storage $storage,
		?string $defaultRole = null,
	) {
		$this->realm = $realm;
		$this->baseUrl = $baseUrl;
		$this->hostUrl = $hostUrl;
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->frontendClientId = $frontendClientId;
		$this->defaultRoleName = $defaultRole;
		$this->em = $em;
		$this->linkGenerator = $linkGenerator;
		$this->securityUser = $securityUser;
		$this->identityQueryFactory = $identityQueryFactory;
		$this->aclRoleQueryFactory = $aclRoleQueryFactory;
		$this->fancyAdmin = $fancyAdmin;
		$this->session = $session;
		$this->cache = new Cache($storage, 'keycloak-user-search');

		$this->client = new Client(
			[
				'base_uri' => $this->baseUrl,
				'timeout'  => 5.0,
				'verify'   => false
			]
		);
	}

	private function getOpenIdRealmUrl(string $action): string
	{
		return "/realms/$this->realm/protocol/openid-connect/$action";
	}

	private function getAdminRealmUrl(string $action): string
	{
		return "/admin/realms/$this->realm/$action";
	}

	/**
	 * Sestaví login URL na Keycloak (Authorization Code Flow).
	 */
	public function getLoginUrl(?string $backRedirect = null, ?string $loginHint = null, bool $autoFocusPassword = false): string
	{
		$redirectUri = $this->getAuthRedirectUri();

		$url = new Url("$this->hostUrl/realms/$this->realm/protocol/openid-connect/auth");

		if ($backRedirect) {
			$url->setQueryParameter('state', $backRedirect);
		}

		$url->setQueryParameter('client_id', $this->clientId);
		$url->setQueryParameter('response_type', 'code');
		$url->setQueryParameter('redirect_uri', $redirectUri);
		$url->setQueryParameter('scope', 'openid email profile');

		if (!empty($loginHint)) {
			$url->setQueryParameter('login_hint', $loginHint);
		}

		if ($autoFocusPassword) {
			$url->setQueryParameter('ui_locales', 'autofocus-password');
		}

		return (string) $url;
	}

	/**
	 * Vytvoří logoutUrl na Keycloak v případě, že se Uživatel přihlásil přes Keycloak a má nastaveno v Sessioně idToken.
	 */
	public function getLogoutUrl(?string $backRedirect = null): ?string
	{
		if (!$this->session->hasSection(KeycloakSessionSection::SECTION_NAME)) {
			return null;
		}

		$keycloakSession = $this->session->getSection(KeycloakSessionSection::SECTION_NAME);

		if ($keycloakSession->get(KeycloakSessionSection::NON_EXISTING_SSO_LOGIN)) {
			$keycloakSession->remove(KeycloakSessionSection::NON_EXISTING_SSO_LOGIN);
		}

		if (empty($keycloakSession->get(KeycloakSessionSection::ID_TOKEN))) {
			return null;
		}

		if ($backRedirect === null) {
			$backRedirect = $this->linkGenerator->link('//:Portal:Sign:in');
		}

		$logoutUrl = $this->linkGenerator->link('//:Portal:KeycloakAuth:postLogOut');

		$url = new Url("$this->hostUrl/realms/$this->realm/protocol/openid-connect/logout");
		$url->setQueryParameter('post_logout_redirect_uri', $logoutUrl);
		$url->setQueryParameter('state', $backRedirect);
		$url->setQueryParameter('id_token_hint', $keycloakSession->get(KeycloakSessionSection::ID_TOKEN));

		$keycloakSession->remove(KeycloakSessionSection::ID_TOKEN);

		return (string) $url;
	}

	/**
	 * Sestaví URL pro silent login (prompt=none) — pro kontrolu, zda je uživatel v Keycloaku přihlášen.
	 */
	public function getSilentLoginUrl(?string $backRedirect = null, ?string $action = null): string
	{
		$redirectParams = ['instance' => $this->instanceName];
		if ($backRedirect) {
			$redirectParams['backRedirect'] = $backRedirect;
		}

		if ($action === null) {
			$action = 'silentCheck';
		}

		$redirectUrl = $this->linkGenerator->link("//:Portal:KeycloakAuth:$action", $redirectParams);

		$url = new Url("$this->hostUrl/realms/$this->realm/protocol/openid-connect/auth");

		$url->setQueryParameter('client_id', $this->clientId);
		$url->setQueryParameter('response_type', 'code');
		$url->setQueryParameter('redirect_uri', $redirectUrl);
		$url->setQueryParameter('scope', 'openid email profile');
		$url->setQueryParameter('prompt', 'none');

		return (string) $url;
	}

	public function getAuthRedirectUri(): string
	{
		return $this->linkGenerator->link('//:Portal:KeycloakAuth:callback', ['instance' => $this->instanceName]);
	}

	/**
	 * Autentizace uživatele přes password nebo refresh token.
	 * Pokud $password je NULL, pak se 1. parametr bere jako refreshToken.
	 */
	public function authenticate(string $refreshToken, ?string $password = null): ?KeycloakAuthentication
	{
		$formParams =  [
			'grant_type' => 'password',
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret
		];

		if ($password === null) {
			$formParams['refresh_token'] = $refreshToken;
			$formParams['grant_type'] = 'refresh_token';
		} else {
			$formParams['username'] = $refreshToken;
			$formParams['password'] = $password;
			$formParams['scope'] = 'openid profile email';
		}

		try {
			$response = $this->client->post(
				$this->getOpenIdRealmUrl('token'),
				[
					'form_params' => $formParams
				]
			);

			return $this->processAuthResponse($response);
		} catch (RequestException $e) {
			return null;
		}
	}

	/**
	 * Výměna authorization code za tokeny (Authorization Code Flow).
	 */
	public function checkSessionValidity(string $code): ?KeycloakAuthentication
	{
		$redirectUri = $this->getAuthRedirectUri();

		$formParams =  [
			'grant_type' => 'authorization_code',
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'redirect_uri' => $redirectUri,
			'code' => $code,
		];

		try {
			$response = $this->client->post(
				$this->getOpenIdRealmUrl('token'),
				[
					'form_params' => $formParams
				]
			);

			return $this->processAuthResponse($response);
		} catch (RequestException $e) {
			return null;
		}
	}

	private function processAuthResponse(ResponseInterface $response): ?KeycloakAuthentication
	{
		$body = (string) $response->getBody();

		$data = Json::decode($body, true);

		if (!$data['access_token']) {
			return null;
		}

		$userInfo = $this->getUserInfo($data['access_token']);

		// Fallback: pokud getUserInfo HTTP call selhal, zkusíme dekódovat id_token
		if (empty($userInfo) && !empty($data['id_token'])) {
			$userInfo = $this->decodeIdToken($data['id_token']);
		}

		if (empty($userInfo)) {
			return null;
		}

		return new KeycloakAuthentication(
			$data['access_token'],
			$data['refresh_token'] ?? null,
			$data['expires_in'],
			$data['refresh_expires_in'],
			$userInfo,
			$data['id_token'] ?? null,
		);
	}

	/**
	 * Dekóduje payload z JWT id_token jako fallback pro getUserInfo.
	 * Nevaliduje podpis – slouží pouze pro extrakci user claims v případě,
	 * kdy /userinfo endpoint není dostupný.
	 */
	private function decodeIdToken(string $idToken): ?array
	{
		try {
			$parts = explode('.', $idToken);
			if (count($parts) !== 3) {
				return null;
			}

			$payload = base64_decode(strtr($parts[1], '-_', '+/'));

			return Json::decode($payload, true);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Přihlásí nebo zaregistruje uživatele na základě Keycloak autentizace.
	 * Vyhledání probíhá výhradně podle emailu.
	 */
	public function loginOrRegisterUser(KeycloakAuthentication $keycloakAuthentication): void
	{
		$userInfo = $keycloakAuthentication->getUserInfo();
		$email = $userInfo['email'] ?? null;

		if (empty($email)) {
			throw new \Nette\Security\AuthenticationException('Keycloak user has no email.');
		}

		$identity = $this->identityQueryFactory->create()
			->byEmail($email)
			->fetchOneOrNull();

		if ($identity === null) {
			$identity = $this->createIdentity($userInfo);
		}

		if ($keycloakAuthentication->getIdToken()) {
			$keycloakSession = $this->session->getSection(KeycloakSessionSection::SECTION_NAME);
			$keycloakSession->set(KeycloakSessionSection::ID_TOKEN, $keycloakAuthentication->getIdToken());
		}

		$this->securityUser->login($identity, context: $this->fancyAdmin->getContext());
	}

	/**
	 * Najde Sso entitu podle názvu této instance.
	 */
	protected function findSsoEntity(): ?Sso
	{
		$ssoClass = $this->em->findEntityClassByInterface(Sso::class);
		return $this->em->getRepository($ssoClass)->findOneBy(['name' => $this->instanceName]);
	}

	/**
	 * Vytvoří novou identitu z Keycloak user info.
	 * Přiřadí SSO instanci a default roli (z configu, nebo fallback na role navázané na SSO).
	 * Metoda je protected, aby ji bylo možné přepsat v konkrétním projektu.
	 */
	protected function createIdentity(array $userInfo): Identity
	{
		$identityClass = $this->em->findEntityClassByInterface(Identity::class);

		/** @var Identity $identity */
		$identity = new $identityClass();
		$identity->setEmail($userInfo['email']);
		$identity->setFirstName($userInfo['given_name'] ?? null);
		$identity->setLastName($userInfo['family_name'] ?? null);

		// Přiřadit SSO instanci
		$sso = $this->findSsoEntity();
		if ($sso !== null) {
			$identity->setSso($sso);
		}

		// Přiřadit roli — buď defaultRole z configu, nebo role navázané na SSO
		if ($this->defaultRoleName !== null) {
			$role = $this->aclRoleQueryFactory->create()
				->byName($this->defaultRoleName)
				->fetchOneOrNull();

			if ($role !== null) {
				$identity->addRole($role);
			}
		} elseif ($sso !== null) {
			$roles = $this->aclRoleQueryFactory->create()
				->bySso($sso)
				->fetchAll();

			foreach ($roles as $role) {
				$identity->addRole($role);
			}
		}

		$this->em->persist($identity);
		$this->em->flush();

		return $identity;
	}

	/**
	 * Vyhledá uživatele v Keycloaku podle emailu (Admin API).
	 */
	public function findUser(string $email): ?KeycloakUser
	{
		$adminAccessToken = $this->getAdminAccessToken();

		if ($adminAccessToken === null) {
			return null;
		}

		$data = $this->cache->load($this->instanceName . ':' . $email, function (&$dependencies) use ($adminAccessToken, $email) {
			$dependencies[Cache::Expire] = '1 hour';

			$query = $this->client->get(
				$this->getAdminRealmUrl('users'),
				[
					'query' => [
						'email' => $email,
						'exact' => 'true'
					],
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()]
				]
			);
			$body = (string) $query->getBody();

			$data = Json::decode($body, true);
			if (empty($data)) {
				$query = $this->client->get(
					$this->getAdminRealmUrl('users'),
					[
						'query' => [
							'username' => $email,
							'exact' => 'true'
						],

						'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()]
					]
				);

				$body = (string) $query->getBody();

				$data = Json::decode($body, true);
			}

			return $data;
		});

		if (isset($data[0])) {
			$data = $data[0];

			return new KeycloakUser(
				$data['id'],
				$data['username'],
				$data['firstName'] ?? null,
				$data['lastName'] ?? null,
				$data['email'] ?? null
			);
		}

		return null;
	}

	/**
	 * Získá admin access token přes client_credentials grant.
	 */
	public function getAdminAccessToken(): ?KeycloakAdminAccessToken
	{
		if ($this->adminAccessToken !== null && $this->adminAccessToken->isValid()) {
			return $this->adminAccessToken;
		}

		$formParams = [
			'grant_type' => 'client_credentials',
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret
		];

		try {
			$response = $this->client->post(
				$this->getOpenIdRealmUrl('token'),
				[
					'form_params' => $formParams
				]
			);

			$body = (string) $response->getBody();

			$data = Json::decode($body, true);

			if (empty($data['access_token'])) {
				return null;
			}

			$this->adminAccessToken = new KeycloakAdminAccessToken($data['access_token'], $data['expires_in']);

			return $this->adminAccessToken;
		} catch (RequestException $e) {
			return null;
		}
	}

	/**
	 * Získá informace o uživateli z /userinfo endpointu.
	 */
	public function getUserInfo(string $accessToken): ?array
	{
		try {
			$response = $this->client->get(
				$this->getOpenIdRealmUrl('userinfo'),
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $accessToken,
					]
				]
			);

			$body = (string) $response->getBody();

			return Json::decode($body, true);
		} catch (RequestException $e) {
			return null;
		}
	}

	/**
	 * Zaregistruje uživatele v Keycloaku.
	 * Pokud uživatel s daným emailem již existuje, vrátí existujícího.
	 *
	 * @param Identity $identity Lokální identita
	 * @param string|null $password Heslo — pokud je null, uživatel se vytvoří bez hesla
	 * @param bool $temporaryPassword Pokud true, Keycloak vynutí změnu hesla při prvním přihlášení
	 * @return KeycloakUser|null Vytvořený nebo existující uživatel, null při chybě
	 */
	public function registerUser(Identity $identity, ?string $password = null, bool $temporaryPassword = false): ?KeycloakUser
	{
		$email = $identity->getEmail();
		if (empty($email)) {
			return null;
		}

		// Check jestli user už v Keycloaku existuje
		$existing = $this->findUser($email);
		if ($existing !== null) {
			return $existing;
		}

		$adminAccessToken = $this->getAdminAccessToken();
		if ($adminAccessToken === null) {
			return null;
		}

		$userData = [
			'username' => $email,
			'email' => $email,
			'firstName' => $identity->getFirstName() ?? '',
			'lastName' => $identity->getLastName() ?? '',
			'enabled' => true,
			'emailVerified' => true,
		];

		if ($password !== null) {
			$userData['credentials'] = [
				[
					'type' => 'password',
					'value' => $password,
					'temporary' => $temporaryPassword,
				],
			];
		}

		try {
			$response = $this->client->post(
				$this->getAdminRealmUrl('users'),
				[
					'json' => $userData,
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			if ($response->getStatusCode() === 201) {
				// Invalidovat cache pro tohoto uživatele
				$this->cache->remove($this->instanceName . ':' . $email);

				// Získat vytvořeného uživatele (response obsahuje jen Location header)
				return $this->findUser($email);
			}

			return null;
		} catch (RequestException $e) {
			// 409 Conflict = user already exists
			if ($e->getResponse()?->getStatusCode() === 409) {
				$this->cache->remove($this->instanceName . ':' . $email);
				return $this->findUser($email);
			}

			return null;
		}
	}

	/**
	 * Aktualizuje uživatele v Keycloaku podle lokální identity.
	 * Synchronizuje email, jméno a příjmení.
	 *
	 * @param Identity $identity Lokální identita
	 * @param string|null $keycloakUserId Keycloak user ID — pokud null, vyhledá se podle emailu
	 * @return KeycloakUser|null Aktualizovaný uživatel, null pokud nebyl nalezen nebo při chybě
	 */
	public function updateUser(Identity $identity, ?string $keycloakUserId = null): ?KeycloakUser
	{
		$email = $identity->getEmail();
		if (empty($email)) {
			return null;
		}

		$adminAccessToken = $this->getAdminAccessToken();
		if ($adminAccessToken === null) {
			return null;
		}

		if ($keycloakUserId === null) {
			$existingUser = $this->findUser($email);
			if ($existingUser === null) {
				return null;
			}
			$keycloakUserId = $existingUser->getId();
		}

		$userData = [
			'email' => $email,
			'username' => $email,
			'firstName' => $identity->getFirstName() ?? '',
			'lastName' => $identity->getLastName() ?? '',
		];

		try {
			$this->client->put(
				$this->getAdminRealmUrl("users/$keycloakUserId"),
				[
					'json' => $userData,
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			$this->cache->remove($this->instanceName . ':' . $email);
			return $this->findUser($email);
		} catch (RequestException $e) {
			return null;
		}
	}

	/**
	 * Deaktivuje uživatele v Keycloaku (nastaví enabled = false).
	 *
	 * @param Identity $identity Lokální identita
	 * @return bool True pokud byl uživatel deaktivován, false pokud nebyl nalezen nebo při chybě
	 */
	public function disableUser(Identity $identity): bool
	{
		$email = $identity->getEmail();
		if (empty($email)) {
			return false;
		}

		$adminAccessToken = $this->getAdminAccessToken();
		if ($adminAccessToken === null) {
			return false;
		}

		$existingUser = $this->findUser($email);
		if ($existingUser === null) {
			return false;
		}

		try {
			$this->client->put(
				$this->getAdminRealmUrl("users/{$existingUser->getId()}"),
				[
					'json' => ['enabled' => false],
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			$this->cache->remove($this->instanceName . ':' . $email);
			return true;
		} catch (RequestException $e) {
			return false;
		}
	}

	/**
	 * Aktivuje uživatele v Keycloaku (nastaví enabled = true).
	 *
	 * @param Identity $identity Lokální identita
	 * @return bool True pokud byl uživatel aktivován
	 */
	public function enableUser(Identity $identity): bool
	{
		$email = $identity->getEmail();
		if (empty($email)) {
			return false;
		}

		$adminAccessToken = $this->getAdminAccessToken();
		if ($adminAccessToken === null) {
			return false;
		}

		$existingUser = $this->findUser($email);
		if ($existingUser === null) {
			return false;
		}

		try {
			$this->client->put(
				$this->getAdminRealmUrl("users/{$existingUser->getId()}"),
				[
					'json' => ['enabled' => true],
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			$this->cache->remove($this->instanceName . ':' . $email);
			return true;
		} catch (RequestException $e) {
			return false;
		}
	}

	/**
	 * Nastaví heslo uživateli v Keycloaku.
	 *
	 * @param Identity $identity Lokální identita
	 * @param string $password Nové heslo
	 * @param bool $temporary Pokud true, Keycloak vynutí změnu hesla při prvním přihlášení
	 * @return bool True pokud bylo heslo nastaveno
	 */
	public function setUserPassword(Identity $identity, string $password, bool $temporary = false): bool
	{
		$email = $identity->getEmail();
		if (empty($email)) {
			return false;
		}

		$adminAccessToken = $this->getAdminAccessToken();
		if ($adminAccessToken === null) {
			return false;
		}

		$existingUser = $this->findUser($email);
		if ($existingUser === null) {
			return false;
		}

		try {
			$this->client->put(
				$this->getAdminRealmUrl("users/{$existingUser->getId()}/reset-password"),
				[
					'json' => [
						'type' => 'password',
						'value' => $password,
						'temporary' => $temporary,
					],
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			return true;
		} catch (RequestException $e) {
			return false;
		}
	}

	public function getInstanceName(): string
	{
		return $this->instanceName;
	}

	public function setInstanceName(string $instanceName): void
	{
		$this->instanceName = $instanceName;
	}

	public function getHostUrl(): string
	{
		return $this->hostUrl;
	}

	public function getClientId(): string
	{
		return $this->clientId;
	}

	public function getRealm(): string
	{
		return $this->realm;
	}

	public function getFrontendClientId(): string
	{
		return $this->frontendClientId;
	}
}
