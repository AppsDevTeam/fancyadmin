<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security\Passkey;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Entities\Passkey;
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
 * - identity navázané na Keycloak SSO se přes passkey přihlásit ani registrovat nesmí
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
	 * @throws PasskeyException pro identitu navázanou na SSO
	 */
	public function getRegistrationArgs(Identity $identity): stdClass
	{
		$this->assertEnabled();
		$this->assertNotSso($identity);

		// Lazy vygenerování opaque user handle — autentikátoru nikdy neposíláme interní ID identity
		if ($identity->getPasskeyUserHandle() === null) {
			$identity->setPasskeyUserHandle(random_bytes(32));
			$this->em->flush();
		}

		$excludeCredentialIds = [];
		/** @var Passkey $passkey */
		foreach ($this->getPasskeyQueryFactory()->create()->disableSecurityFilter()->disableAccountFilter()->byIdentity($identity)->fetch() as $passkey) {
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
		$this->assertNotSso($identity);

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
	 * odmítá SSO identity a neaktivní identity. Po úspěchu bumpne signCount a lastUsedAt.
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

		$identity = $passkey->getIdentity();

		if ($userHandle !== null && $userHandle !== '') {
			$storedHandle = $identity->getPasskeyUserHandle();
			if ($storedHandle === null || !hash_equals($storedHandle, $userHandle)) {
				throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.unknownKey'));
			}
		}

		$this->assertNotSso($identity);

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

	/**
	 * @throws PasskeyException pokud je identita navázaná na Keycloak SSO
	 */
	public function assertNotSso(Identity $identity): void
	{
		if ($identity->getSso() !== null) {
			throw new PasskeyException($this->translator->translate('fcadmin.passkeys.errors.ssoAccount'));
		}
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

		$host = (string) preg_replace('~^https?://~', '', $this->fancyAdmin->getAdminHostPath());
		$host = explode('/', $host)[0];
		return explode(':', $host)[0];
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
