<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use RuntimeException;

/**
 * Šifrování tajných hodnot uložených v DB (konfigurace typu secret, README - Konfigurace).
 *
 * Hodnota v DB je čitelná pro každého, kdo vidí databázi, zálohu nebo auditní stopu
 * (change_log) - proto se ukládá jen zašifrovaná (libsodium secretbox, autentizované
 * šifrování). Klíč je serverové tajemství z konfigurace (`fancyadmin.secretKey`, typicky
 * z env), v DB nikdy není. Ze zadaného řetězce se odvodí 32 bajtů, takže stačí libovolně
 * dlouhé náhodné heslo.
 *
 * Výměna klíče = hodnoty zašifrované starým klíčem už nepůjdou přečíst (decrypt vyhodí
 * výjimku) a je potřeba je zadat znovu.
 */
final class SecretCipher
{
	private const string PREFIX = 'secretbox:v1:';

	public function __construct(
		#[\SensitiveParameter]
		private readonly ?string $secretKey,
	) {
	}

	public function isConfigured(): bool
	{
		return trim((string) $this->secretKey) !== '';
	}

	/**
	 * @throws RuntimeException bez nastaveného klíče
	 */
	public function encrypt(#[\SensitiveParameter] string $plaintext): string
	{
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->getKey()));
	}

	/**
	 * @throws RuntimeException bez klíče, u poškozené hodnoty nebo hodnoty zašifrované jiným klíčem
	 */
	public function decrypt(string $ciphertext): string
	{
		if (!self::isEncrypted($ciphertext)) {
			throw new RuntimeException('Hodnota není zašifrovaná přes SecretCipher.');
		}

		$raw = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
		if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			throw new RuntimeException('Zašifrovaná hodnota je poškozená.');
		}

		$plaintext = sodium_crypto_secretbox_open(
			substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			$this->getKey(),
		);
		if ($plaintext === false) {
			throw new RuntimeException('Hodnotu nejde dešifrovat - je zašifrovaná jiným klíčem (fancyadmin.secretKey), nebo je poškozená.');
		}

		return $plaintext;
	}

	public static function isEncrypted(?string $value): bool
	{
		return $value !== null && str_starts_with($value, self::PREFIX);
	}

	private function getKey(): string
	{
		if (!$this->isConfigured()) {
			throw new RuntimeException('Chybí klíč pro šifrování tajných hodnot (fancyadmin.secretKey).');
		}

		return sodium_crypto_generichash((string) $this->secretKey, 'fancyadmin-secret', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
	}
}
