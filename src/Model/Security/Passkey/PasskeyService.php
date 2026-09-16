<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Passkey;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Passkey;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use ADT\FancyAdmin\Model\FancyAdmin;
use ADT\FancyAdmin\Model\Queries\Factories\PasskeyQueryFactory;
use DateTimeImmutable;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Localization\Translator;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Obal nad lbuchs/webauthn pro přihlašování a registraci passkeys (WebAuthn).
 *
 * - challenge se drží v Nette session (one-shot, expirace 5 minut),
 *   oddělené klíče pro create (registrace) a get (login) ceremony
 * - attestation format `none`, resident key required, user verification required
 * - login je usernameless (prázdné allowCredentials) — credential-first lookup
 * - klíč si může registrovat i identita navázaná na Keycloak SSO (aby měla 2FA připravené
 *   ještě před zrušením SSO); uživatele s povinným Keycloak loginem přesměruje na Keycloak
 *   místo passkey loginu SignInFormTrait
 * - role s `needs2fa` vynucuje přihlášení výhradně klíčem (isPasskeyRequired()); precedence
 *   je SSO > 2FA > heslo a při vypnutém `passkeyEnabled` je flag inertní (README 19.8)
 * - všechny binárky v JSON args jsou base64url (ByteBuffer::$useBase64UrlEncoding)
 */
class PasskeyService
{
	private const int TIMEOUT_SECONDS = 60;

	private const int NAME_MAX_LENGTH = 64;

	public function __construct(
		protected EntityManager $em,
		protected Session $session,
		protected FancyAdmin $fancyAdmin,
		protected Translator $translator,
		// nullable — passkey infrastruktura (entita, query, factory) je v projektu volitelná,
		// služba se ale musí dát vytvořit vždy (injectuje se v traitech přes PasskeyServiceInject)
		protected ?PasskeyQueryFactory $passkeyQueryFactory = null,
	) {}

	/**
	 * Vygeneruje PublicKeyCredentialCreationOptions pro registraci nového klíče.
	 * Challenge se uloží do session (one-shot, expirace 5 minut).
	 *
	 * @throws PasskeyException
	 */
	public function getRegistrationArgs(Identity $identity): stdClass
	{
		$this->assertEnabled();
		$identity = $this->assertHasPasskeys($identity);

		// Lazy vygenerování opaque user handle — autentikátoru nikdy neposíláme interní ID identity
		if ($identity->getPasskeyUserHandle() === null) {
			$identity->setPasskeyUserHandle(random_bytes(32));
			$this->em->flush();
		}

		$excludeCredentialIds = [];
		foreach ($identity->getPasskeys() as $passkey) {
			$excludeCredentialIds[] = $passkey->getCredentialId();
		}

		$webAuthn = $this->createWebAuthn();
		$args = $webAuthn->getCreateArgs(
			$identity->getPasskeyUserHandle(),
			(string) $identity->getEmail(),
			$identity->getFullName() !== '' ? $identity->getFullName() : (string) $identity->getEmail(),
			self::TIMEOUT_SECONDS,
			requireResidentKey: true,
			requireUserVerification: 'required',
			excludeCredentialIds: $excludeCredentialIds,
		);

		$this->storeChallenge(PasskeySessionSection::CREATE_CHALLENGE, $webAuthn->getChallenge()->getBinaryString());

		return $args;
	}

	/**
	 * Ověří odpověď autentikátoru na create ceremony a persistuje nový klíč.
	 *
	 * @param string $clientDataJSON raw binary (dekódované z base64url)
	 * @param string $attestationObject raw binary (dekódované z base64url)
	 * @param string $name uživatelský název klíče (povinný)
	 * @param string[]|null $transports transports z browseru (credential.response.transports)
	 * @throws PasskeyException
	 */
	public function processRegistration(
		Identity $identity,
		string $clientDataJSON,
		string $attestationObject,
		string $name,
		?array $transports = null,
	): Passkey
	{
		$this->assertEnabled();

		$name = $this->normalizeName($name);

		$challenge = $this->consumeChallenge(PasskeySessionSection::CREATE_CHALLENGE);

		$webAuthn = $this->createWebAuthn();
		try {
			$data = $webAuthn->processCreate(
				$clientDataJSON,
				$attestationObject,
				new ByteBuffer($challenge),
				requireUserVerification: true,
			);
		} catch (WebAuthnException) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.invalidKey'));
		}

		$credentialId = $data->credentialId;

		if ($this->getPasskeyQueryFactory()->create()->disableSecurityFilter()->disableAccountFilter()->byCredentialId($credentialId)->count() > 0) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.alreadyRegistered'));
		}

		$aaguid = $data->AAGUID instanceof ByteBuffer ? $data->AAGUID->getBinaryString() : ($data->AAGUID ?: null);
		if ($aaguid !== null && trim($aaguid, "\0") === '') {
			$aaguid = null;
		}

		$transports = $transports === null ? null : array_values(array_filter(array_map('strval', $transports)));

		$passkeyClass = $this->em->findEntityClassByInterface(Passkey::class);
		/** @var Passkey $passkey */
		$passkey = new $passkeyClass();
		$passkey
			->setIdentity($identity)
			->setName($name)
			->setCredentialId($credentialId)
			->setPublicKey($data->credentialPublicKey)
			->setSignCount($webAuthn->getSignatureCounter() ?? 0)
			->setAaguid($aaguid)
			->setTransports($transports)
			->setBackupEligible($data->isBackupEligible ?? null)
			->setBackupState($data->isBackedUp ?? null);

		$this->em->persist($passkey);
		$this->em->flush();

		return $passkey;
	}

	/**
	 * Vygeneruje PublicKeyCredentialRequestOptions pro usernameless login
	 * (prázdné allowCredentials — prohlížeč nabídne discoverable credentials).
	 */
	public function getLoginArgs(): stdClass
	{
		$this->assertEnabled();

		$webAuthn = $this->createWebAuthn();
		$args = $webAuthn->getGetArgs(
			[],
			self::TIMEOUT_SECONDS,
			requireUserVerification: 'required',
		);

		$this->storeChallenge(PasskeySessionSection::GET_CHALLENGE, $webAuthn->getChallenge()->getBinaryString());

		return $args;
	}

	/**
	 * Ověří assertion z get ceremony a vrátí identitu klíče.
	 * Credential-first lookup podle credentialId, kontrola userHandle přes hash_equals,
	 * odmítá neaktivní identity. Po úspěchu bumpne signCount a lastUsedAt.
	 *
	 * @param string $credentialId raw binary
	 * @param string $clientDataJSON raw binary
	 * @param string $authenticatorData raw binary
	 * @param string $signature raw binary
	 * @param string|null $userHandle raw binary (pokud ho autentikátor poslal)
	 * @throws PasskeyException
	 */
	public function processLogin(
		string $credentialId,
		string $clientDataJSON,
		string $authenticatorData,
		string $signature,
		?string $userHandle = null,
	): Identity
	{
		$this->assertEnabled();

		$challenge = $this->consumeChallenge(PasskeySessionSection::GET_CHALLENGE);

		/** @var Passkey|null $passkey */
		$passkey = $this->getPasskeyQueryFactory()->create()
			->disableSecurityFilter()
			->disableAccountFilter()
			->byCredentialId($credentialId)
			->fetchOneOrNull();

		if ($passkey === null) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.unknownKey'));
		}

		$identity = $this->assertHasPasskeys($passkey->getIdentity());

		if ($userHandle !== null && $userHandle !== '') {
			$storedHandle = $identity->getPasskeyUserHandle();
			if ($storedHandle === null || !hash_equals($storedHandle, $userHandle)) {
				throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.unknownKey'));
			}
		}

		if (!$identity->getIsActive()) {
			throw new PasskeyException($this->translator->translate('fcadmin.appGeneral.exceptions.inactiveUser'));
		}

		$webAuthn = $this->createWebAuthn();
		try {
			$webAuthn->processGet(
				$clientDataJSON,
				$authenticatorData,
				$signature,
				$passkey->getPublicKey(),
				new ByteBuffer($challenge),
				$passkey->getSignCount(),
				requireUserVerification: true,
			);
		} catch (WebAuthnException) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.unknownKey'));
		}

		$newSignCount = $webAuthn->getSignatureCounter();
		if ($newSignCount !== null) {
			$passkey->setSignCount($newSignCount);
		}
		$passkey->setLastUsedAt(new DateTimeImmutable());
		$this->em->flush();

		return $identity;
	}

	/**
	 * Vyžaduje identita přihlášení klíčem (role s needs2fa)? Pak se heslem nepřihlásí.
	 *
	 * Bez side efektů — volá se v každém requestu z AuthPresenteru.
	 */
	public function isPasskeyRequired(Identity $identity): bool
	{
		if (!$this->fancyAdmin->isPasskeyEnabled()) {
			return false;
		}

		// Role jsou v paměti, proto se čtou první — identita bez flagu nestojí dotaz do DB
		if (!array_any($this->getAllRoles($identity), fn(AclRole $role) => $role->getNeeds2fa())) {
			return false;
		}

		// Precedence SSO > 2FA > heslo: identitu s povinným Keycloak loginem řeší Keycloak.
		// Podmínka je stejná jako v KeycloakManager::getInstanceForIdentity(), ale bez tvrdé
		// závislosti — při vypnutém Keycloaku manager neexistuje a na SSO se neohlížíme.
		if ($this->fancyAdmin->isKeycloakEnabled() && $this->fancyAdmin->getKeycloakManager()?->getInstanceForIdentity($identity) !== null) {
			return false;
		}

		return true;
	}

	public function hasPasskeys(Identity $identity): bool
	{
		// Entita bez HasPasskeys žádný klíč mít nemůže (při passkeyEnabled to hlídá extension)
		return $identity instanceof HasPasskeys && $identity->getPasskeys() !== [];
	}

	/**
	 * Bootstrap okno: identita klíč vyžaduje, ale ještě žádný nemá — heslem se přihlásí,
	 * ale jen na stránku Můj účet, kde si klíč zaregistruje (README 19.8).
	 */
	public function isEnrollmentPending(Identity $identity): bool
	{
		return $this->isPasskeyRequired($identity) && !$this->hasPasskeys($identity);
	}

	public function markPasskeySession(): void
	{
		$this->getSessionSection()->set(PasskeySessionSection::PASSKEY_SESSION, true);
	}

	public function isPasskeySession(): bool
	{
		return $this->getSessionSection()->get(PasskeySessionSection::PASSKEY_SESSION) === true;
	}

	/**
	 * Volá se při přihlášení heslem: odhlášení maže jen auth cookie, ne session, takže
	 * by si heslová session vzala marker po dřívějším přihlášení klíčem ve stejném prohlížeči.
	 */
	public function clearPasskeySession(): void
	{
		if (!$this->fancyAdmin->isPasskeyEnabled()) {
			return;
		}

		$this->getSessionSection()->remove(PasskeySessionSection::PASSKEY_SESSION);
	}

	/**
	 * Vlastní role identity + role všech jejích profilů.
	 *
	 * Identity::getRoles() vrací jen role profilu vybraného účtu, takže uživatel s více
	 * profily by 2FA obešel přepnutím účtu — proto se doplňují role všech profilů.
	 *
	 * @return AclRole[]
	 */
	protected function getAllRoles(Identity $identity): array
	{
		// Nette IIdentity::getRoles() je typované jako string[], fancyadmin vrací AclRole[]
		/** @var AclRole[] $roles */
		$roles = $identity->getRoles();

		foreach ($identity->getProfiles() as $profile) {
			$roles = array_merge($roles, $profile->getRoles());
		}

		return $roles;
	}

	/**
	 * Server-side vynucení opt-in configu (fancyadmin: passkeyEnabled) —
	 * musí fungovat i kdyby UI někde zůstalo viditelné.
	 *
	 * @throws PasskeyException pokud passkeys nejsou v configu zapnuté
	 */
	public function assertEnabled(): void
	{
		if (!$this->fancyAdmin->isPasskeyEnabled()) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.unavailable'));
		}
	}

	/**
	 * @throws RuntimeException pokud entita Identity nepodporuje passkeys —
	 * chyba konfigurace, ne uživatele
	 */
	protected function assertHasPasskeys(Identity $identity): Identity&HasPasskeys
	{
		if (!$identity instanceof HasPasskeys) {
			throw new RuntimeException('Entita ' . $identity::class . ' neimplementuje ' . HasPasskeys::class . ' — přidejte `use IdentityPasskeysTrait` a `implements HasPasskeys` podle README (sekce 19).');
		}

		return $identity;
	}

	/**
	 * @throws RuntimeException pokud projekt nemá zaregistrovanou passkey infrastrukturu —
	 * chyba konfigurace, ne uživatele (FancyAdminExtension ji při passkeyEnabled hlídá už při kompilaci)
	 */
	protected function getPasskeyQueryFactory(): PasskeyQueryFactory
	{
		if ($this->passkeyQueryFactory === null) {
			throw new RuntimeException('V projektu chybí implementace ' . PasskeyQueryFactory::class . ' — vytvořte entitu Passkey, query a factory podle README (sekce 19).');
		}

		return $this->passkeyQueryFactory;
	}

	protected function createWebAuthn(): WebAuthn
	{
		try {
			// 4. parametr: base64url pro všechny binárky v JSON args (ByteBuffer::$useBase64UrlEncoding)
			return new WebAuthn($this->getRpName(), $this->getRpId(), ['none'], true);
		} catch (Throwable) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.unavailable'));
		}
	}

	/**
	 * rpId = doména admin hostu. Explicitně z configu (passkeyRpId),
	 * jinak odvozeno z adminHostPath (bez schématu, cesty a portu).
	 */
	public function getRpId(): string
	{
		if ($rpId = $this->fancyAdmin->getPasskeyRpId()) {
			return $rpId;
		}

		return self::deriveRpId($this->fancyAdmin->getAdminHostPath());
	}

	/**
	 * Odvodí rpId z admin host path — zahodí schéma, cestu i port.
	 * Vrací prázdný string, když se odvodit nedá.
	 *
	 * Statické, aby stejnou logiku mohla použít i kontrola konfigurace při kompilaci
	 * kontejneru (FancyAdminExtension) — chybějící rpId se tak pozná dřív než za běhu.
	 */
	public static function deriveRpId(?string $adminHostPath): string
	{
		$host = (string) preg_replace('~^https?://~', '', (string) $adminHostPath);

		return explode(':', explode('/', $host)[0])[0];
	}

	public function getRpName(): string
	{
		return $this->fancyAdmin->getPasskeyRpName();
	}

	protected function storeChallenge(string $key, string $challenge): void
	{
		$this->getSessionSection()->set($key, $challenge, PasskeySessionSection::CHALLENGE_EXPIRATION);
	}

	/**
	 * One-shot vyzvednutí challenge — po přečtení se ze session maže.
	 *
	 * @throws PasskeyException pokud challenge chybí nebo expirovala
	 */
	protected function consumeChallenge(string $key): string
	{
		$section = $this->getSessionSection();
		$challenge = $section->get($key);
		$section->remove($key);

		if (!is_string($challenge) || $challenge === '') {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.expiredChallenge'));
		}

		return $challenge;
	}

	private function getSessionSection(): SessionSection
	{
		return $this->session->getSection(PasskeySessionSection::SECTION_NAME);
	}

	/**
	 * Ověří a normalizuje název klíče — název je povinný, zkrátí se na délku sloupce.
	 *
	 * @throws PasskeyException pro prázdný název
	 */
	protected function normalizeName(string $name): string
	{
		$name = trim($name);
		if ($name === '') {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.form.errors.nameRequired'));
		}

		return mb_substr($name, 0, self::NAME_MAX_LENGTH);
	}

	/**
	 * Dekódování base64url (WebAuthn JSON serializace) na raw binary.
	 * Vrací null pro nevalidní vstup — volající odpoví přeloženou chybou.
	 */
	public static function base64UrlDecode(?string $data): ?string
	{
		if ($data === null || $data === '') {
			return null;
		}
		$decoded = base64_decode(strtr($data, '-_', '+/'), true);
		return $decoded === false ? null : $decoded;
	}
}
