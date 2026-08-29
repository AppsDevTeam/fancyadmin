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
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
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
	/** Platnost rozpracovaného autorizačního requestu (state + PKCE verifier) v session */
	private const int AUTH_STATE_TTL_SECONDS = 600;
	/** Max počet souběžně rozpracovaných autorizačních requestů v jedné session */
	private const int AUTH_STATE_MAX_ENTRIES = 10;

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

	private Cache $jwksCache;

	private ?KeycloakAdminAccessToken $adminAccessToken = null;

	private string $instanceName = 'default';

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
		bool $verifySsl = true,
	) {
		$this->realm = $realm;
		$this->baseUrl = $baseUrl;
		$this->hostUrl = $hostUrl;
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->frontendClientId = $frontendClientId;
		$this->em = $em;
		$this->linkGenerator = $linkGenerator;
		$this->securityUser = $securityUser;
		$this->identityQueryFactory = $identityQueryFactory;
		$this->aclRoleQueryFactory = $aclRoleQueryFactory;
		$this->fancyAdmin = $fancyAdmin;
		$this->session = $session;
		$this->cache = new Cache($storage, 'keycloak-user-search');
		$this->jwksCache = new Cache($storage, 'keycloak-jwks');

		// Validace TLS certifikátu musí být zapnutá — přes tento kanál jde výměna code za tokeny
		// i client_secret. Vypnout ji lze config volbou keycloakVerifySsl jen pro lokální vývoj.
		$this->client = new Client(
			[
				'base_uri' => $this->baseUrl,
				'timeout'  => 5.0,
				'verify'   => $verifySsl
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
	 * Sestaví login URL na Keycloak (Authorization Code Flow s PKCE).
	 * Návratová URL ($backRedirect) se neposílá do Keycloaku — uloží se do session
	 * pod náhodný state (CSRF token) a callback si ji vyzvedne po ověření state.
	 */
	public function getLoginUrl(?string $backRedirect = null, ?string $loginHint = null, bool $autoFocusPassword = false): string
	{
		$redirectUri = $this->getAuthRedirectUri();

		[$state, $codeChallenge] = $this->createAuthState($backRedirect);

		$url = new Url("$this->hostUrl/realms/$this->realm/protocol/openid-connect/auth");

		$url->setQueryParameter('state', $state);
		$url->setQueryParameter('client_id', $this->clientId);
		$url->setQueryParameter('response_type', 'code');
		$url->setQueryParameter('redirect_uri', $redirectUri);
		$url->setQueryParameter('scope', 'openid email profile');
		$url->setQueryParameter('code_challenge', $codeChallenge);
		$url->setQueryParameter('code_challenge_method', 'S256');

		if (!empty($loginHint)) {
			$url->setQueryParameter('login_hint', $loginHint);
		}

		if ($autoFocusPassword) {
			$url->setQueryParameter('ui_locales', 'autofocus-password');
		}

		return (string) $url;
	}

	/**
	 * Sestaví URL pro změnu hesla přes Application-Initiated Action (kc_action=UPDATE_PASSWORD).
	 * Keycloak si sám vyžádá re-autentizaci současným heslem, ohlídá password policy i 2FA
	 * a po nastavení nového hesla vrátí uživatele zpět do aplikace (přes auth callback).
	 */
	public function getUpdatePasswordUrl(?string $backRedirect = null, ?string $loginHint = null): string
	{
		$url = new Url($this->getLoginUrl($backRedirect, $loginHint));
		$url->setQueryParameter('kc_action', 'UPDATE_PASSWORD');

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
		// client_id explicitně, aby Keycloak uměl zvalidovat post_logout_redirect_uri i když je
		// KC session už neaktivní (jinak stale id_token_hint nemusí klienta identifikovat → "Invalid redirect URL")
		$url->setQueryParameter('client_id', $this->clientId);

		$keycloakSession->remove(KeycloakSessionSection::ID_TOKEN);

		return (string) $url;
	}

	/**
	 * Sestaví URL pro silent login (prompt=none) — pro kontrolu, zda je uživatel v Keycloaku přihlášen.
	 * Návratová URL putuje přes session (viz getLoginUrl), redirect_uri je statické.
	 */
	public function getSilentLoginUrl(?string $backRedirect = null, ?string $action = null): string
	{
		$redirectUrl = $this->getSilentRedirectUri($action);

		[$state, $codeChallenge] = $this->createAuthState($backRedirect);

		$url = new Url("$this->hostUrl/realms/$this->realm/protocol/openid-connect/auth");

		$url->setQueryParameter('state', $state);
		$url->setQueryParameter('client_id', $this->clientId);
		$url->setQueryParameter('response_type', 'code');
		$url->setQueryParameter('redirect_uri', $redirectUrl);
		$url->setQueryParameter('scope', 'openid email profile');
		$url->setQueryParameter('code_challenge', $codeChallenge);
		$url->setQueryParameter('code_challenge_method', 'S256');
		$url->setQueryParameter('prompt', 'none');

		return (string) $url;
	}

	/**
	 * Sestaví redirect_uri pro silent login. Musí být identické s tím, které se použije
	 * při výměně authorization code za tokeny, jinak Keycloak výměnu odmítne (redirect_uri mismatch).
	 * URI je statické (bez proměnlivých parametrů), aby šlo v Keycloaku vyjmenovat
	 * exact redirect URIs bez wildcardů.
	 */
	public function getSilentRedirectUri(?string $action = null): string
	{
		$action ??= 'silentCheck';

		return $this->linkGenerator->link("//:Portal:KeycloakAuth:$action", ['instance' => $this->instanceName]);
	}

	public function getAuthRedirectUri(): string
	{
		return $this->linkGenerator->link('//:Portal:KeycloakAuth:callback', ['instance' => $this->instanceName]);
	}

	public function getBackchannelLogoutUrl(): string
	{
		return $this->linkGenerator->link('//:Portal:KeycloakAuth:backchannelLogout', ['instance' => $this->instanceName]);
	}

	/**
	 * Vytvoří kontext autorizačního requestu a uloží ho do session:
	 * - state: náhodný CSRF token, callback ho ověří proti session
	 * - code_verifier: PKCE (RFC 7636), k token requestu ho přiloží až callback
	 * - backRedirect: návratová URL — do Keycloaku se neposílá, drží se jen v session
	 *
	 * @return array{string, string} [state, code_challenge]
	 */
	private function createAuthState(?string $backRedirect): array
	{
		$state = bin2hex(random_bytes(16));
		$codeVerifier = self::base64UrlEncode(random_bytes(32));

		$section = $this->session->getSection(KeycloakSessionSection::SECTION_NAME);
		$states = $section->get(KeycloakSessionSection::AUTH_STATES) ?? [];

		// Úklid: expirované requesty pryč, počet rozpracovaných requestů omezen (ochrana proti session bloatu)
		$states = array_filter($states, fn (array $entry) => $entry['time'] + self::AUTH_STATE_TTL_SECONDS > time());
		$states = array_slice($states, -(self::AUTH_STATE_MAX_ENTRIES - 1), null, true);

		$states[$state] = [
			'verifier' => $codeVerifier,
			'backRedirect' => $backRedirect,
			'instance' => $this->instanceName,
			'time' => time(),
		];
		$section->set(KeycloakSessionSection::AUTH_STATES, $states);

		return [$state, self::base64UrlEncode(hash('sha256', $codeVerifier, true))];
	}

	/**
	 * Vyzvedne kontext autorizačního requestu podle state a zneplatní ho (one-time use).
	 * Vrací null, pokud state v session není, patří jiné instanci nebo expiroval —
	 * v tom případě callback nesmí pokračovat (možný CSRF / podvržený request).
	 *
	 * @return array{verifier: string, backRedirect: ?string, instance: string, time: int}|null
	 */
	public function consumeAuthState(?string $state): ?array
	{
		if ($state === null || !$this->session->hasSection(KeycloakSessionSection::SECTION_NAME)) {
			return null;
		}

		$section = $this->session->getSection(KeycloakSessionSection::SECTION_NAME);
		$states = $section->get(KeycloakSessionSection::AUTH_STATES) ?? [];

		$entry = $states[$state] ?? null;
		if ($entry === null) {
			return null;
		}

		unset($states[$state]);
		$section->set(KeycloakSessionSection::AUTH_STATES, $states);

		if ($entry['instance'] !== $this->instanceName || $entry['time'] + self::AUTH_STATE_TTL_SECONDS <= time()) {
			return null;
		}

		return $entry;
	}

	private static function base64UrlEncode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Obnova tokenů přes refresh token.
	 * Pozn.: dřívější authenticate() s grant_type=password (ROPC) bylo odstraněno —
	 * OAuth 2.1 tento grant zakazuje, přihlášení jde vždy přes Authorization Code Flow.
	 */
	public function refreshToken(string $refreshToken): ?KeycloakAuthentication
	{
		$formParams = [
			'grant_type' => 'refresh_token',
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'refresh_token' => $refreshToken,
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

	/**
	 * Výměna authorization code za tokeny (Authorization Code Flow s PKCE).
	 * $codeVerifier musí odpovídat code_challenge z autorizačního requestu
	 * (viz createAuthState/consumeAuthState).
	 */
	public function checkSessionValidity(string $code, ?string $redirectUri = null, ?string $codeVerifier = null): ?KeycloakAuthentication
	{
		$redirectUri ??= $this->getAuthRedirectUri();

		$formParams =  [
			'grant_type' => 'authorization_code',
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'redirect_uri' => $redirectUri,
			'code' => $code,
		];

		if ($codeVerifier !== null) {
			$formParams['code_verifier'] = $codeVerifier;
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
	 * Přihlásí uživatele na základě Keycloak autentizace.
	 * Uživatel musí již existovat v lokální databázi.
	 */
	public function loginUser(KeycloakAuthentication $keycloakAuthentication, bool $autoRegister = false): void
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
            if ($autoRegister) {
                $identity = $this->createIdentity($userInfo);
            } else {
			    throw new \Nette\Security\AuthenticationException('User not found in application.');
            }
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

		// Přiřadit default roli ze Sso entity
		if ($sso?->getDefaultRole() !== null) {
			$identity->addRole($sso->getDefaultRole());
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
	 * Získá uživatele z Keycloaku podle jeho ID (sub claim).
	 */
	public function getUserById(string $keycloakUserId): ?KeycloakUser
	{
		$adminAccessToken = $this->getAdminAccessToken();
		if ($adminAccessToken === null) {
			return null;
		}

		try {
			$response = $this->client->get(
				$this->getAdminRealmUrl("users/$keycloakUserId"),
				[
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			$data = Json::decode((string) $response->getBody(), true);

			return new KeycloakUser(
				$data['id'],
				$data['username'],
				$data['firstName'] ?? null,
				$data['lastName'] ?? null,
				$data['email'] ?? null,
			);
		} catch (RequestException $e) {
			return null;
		}
	}

	/**
	 * Zvaliduje backchannel logout token podle OIDC Back-Channel Logout 1.0 a vrátí claims.
	 * Ověřuje: podpis proti JWKS realmu, iss, aud, exp/iat (řeší JWT::decode), events claim,
	 * absenci nonce, přítomnost sub/sid a jednorázovost jti (replay ochrana).
	 *
	 * Vrací null pro nevalidní token — endpoint pak nesmí nic odhlašovat, jinak by šel
	 * podvrženým POSTem odhlásit libovolný uživatel.
	 *
	 * Vyžaduje firebase/php-jwt. Bez něj validace selže (fail closed).
	 */
	public function validateLogoutToken(string $logoutToken): ?array
	{
		if (!class_exists(JWT::class)) {
			return null;
		}

		$claims = $this->decodeAndVerifySignature($logoutToken, $typHeader);
		if ($claims === null) {
			return null;
		}

		// typ header: OIDC spec předepisuje logout+jwt; starší KC posílají JWT nebo nic
		if ($typHeader !== null && !in_array($typHeader, ['logout+jwt', 'JWT'], true)) {
			return null;
		}

		// iss — issuer realmu; token může nést public (hostUrl) i interní (baseUrl) variantu
		$allowedIssuers = [
			rtrim($this->hostUrl, '/') . "/realms/$this->realm",
			rtrim($this->baseUrl, '/') . "/realms/$this->realm",
		];
		if (!in_array($claims['iss'] ?? null, $allowedIssuers, true)) {
			return null;
		}

		// aud — musí obsahovat náš client_id
		if (!in_array($this->clientId, (array) ($claims['aud'] ?? []), true)) {
			return null;
		}

		// events — musí obsahovat backchannel-logout event
		if (!isset($claims['events']['http://schemas.openid.net/event/backchannel-logout'])) {
			return null;
		}

		// logout token nesmí obsahovat nonce (odlišení od id_tokenu) a musí identifikovat session/uživatele
		if (isset($claims['nonce'])) {
			return null;
		}
		if (empty($claims['sub']) && empty($claims['sid'])) {
			return null;
		}

		// replay ochrana — každé jti přijmeme jen jednou
		if (!empty($claims['jti'])) {
			$jtiKey = "jti:$this->instanceName:{$claims['jti']}";
			if ($this->jwksCache->load($jtiKey) !== null) {
				return null;
			}
			$this->jwksCache->save($jtiKey, true, [Cache::Expire => '10 minutes']);
		}

		return $claims;
	}

	/**
	 * Ověří podpis JWT proti JWKS realmu a vrátí claims jako pole.
	 * Při selhání (např. rotace podpisových klíčů v KC) jednou obnoví JWKS cache a zkusí znovu.
	 */
	private function decodeAndVerifySignature(string $jwt, ?string &$typHeader = null): ?array
	{
		foreach ([false, true] as $forceRefresh) {
			try {
				if ($forceRefresh) {
					$this->jwksCache->remove("jwks:$this->instanceName");
				}

				$headers = new \stdClass();
				$decoded = JWT::decode($jwt, JWK::parseKeySet($this->getJwks()), $headers);
				$typHeader = $headers->typ ?? null;

				// převod vnořených stdClass (events apod.) na pole
				return Json::decode(Json::encode($decoded), true);
			} catch (\Throwable $e) {
				if ($forceRefresh) {
					return null;
				}
			}
		}

		return null;
	}

	/**
	 * Vrátí JWKS (podpisové klíče) realmu, cachované na 1 hodinu.
	 */
	private function getJwks(): array
	{
		return $this->jwksCache->load("jwks:$this->instanceName", function (&$dependencies) {
			$dependencies[Cache::Expire] = '1 hour';

			$response = $this->client->get($this->getOpenIdRealmUrl('certs'));

			$jwks = Json::decode((string) $response->getBody(), true);

			// jen podpisové klíče — KC vrací i šifrovací (use=enc), které pro validaci nechceme
			$jwks['keys'] = array_values(array_filter(
				$jwks['keys'] ?? [],
				fn (array $key) => ($key['use'] ?? 'sig') === 'sig'
			));

			return $jwks;
		});
	}

	/**
	 * Zaregistruje uživatele v Keycloaku.
	 * Pokud uživatel s daným emailem již existuje, vrátí existujícího.
	 *
	 * @param Identity $identity Lokální identita
	 * @param string|null $password Heslo — pokud je null, uživatel se vytvoří bez hesla a Keycloak ho vyzve k nastavení při prvním přihlášení
	 * @param bool $temporaryPassword Pokud true, Keycloak vynutí změnu hesla při prvním přihlášení
	 * @param bool $requirePasswordSetup Pokud true a $password je null, Keycloak vyzve uživatele k nastavení hesla při prvním přihlášení
	 * @return KeycloakUser|null Vytvořený nebo existující uživatel, null při chybě
	 */
	public function registerUser(Identity $identity, ?string $password = null, bool $temporaryPassword = false, bool $requirePasswordSetup = true): ?KeycloakUser
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
		} elseif ($requirePasswordSetup) {
			$userData['credentials'] = [
				[
					'type' => 'password',
					'value' => bin2hex(random_bytes(32)),
					'temporary' => true,
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
	 * Odešle email pro reset hesla přes Keycloak (execute-actions-email).
	 * Keycloak pošle svůj email s odkazem na formulář pro nastavení nového hesla.
	 *
	 * @param Identity $identity Lokální identita
	 * @param string|null $redirectUri URL kam přesměrovat po nastavení hesla
	 * @return bool True pokud byl email odeslán
	 */
	public function sendPasswordResetEmail(Identity $identity, ?string $redirectUri = null): bool
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

		$queryParams = [];
		if ($redirectUri !== null) {
			$queryParams['redirect_uri'] = $redirectUri;
			$queryParams['client_id'] = $this->clientId;
		}

		try {
			$this->client->put(
				$this->getAdminRealmUrl("users/{$existingUser->getId()}/execute-actions-email"),
				[
					'query' => $queryParams,
					'json' => ['UPDATE_PASSWORD'],
					'headers' => ['Authorization' => 'Bearer ' . $adminAccessToken->getToken()],
				]
			);

			return true;
		} catch (RequestException $e) {
			return false;
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
