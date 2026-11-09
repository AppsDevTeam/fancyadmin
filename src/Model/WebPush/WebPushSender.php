<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\WebPush;

/**
 * Standardní Web Push (RFC 8030) s identifikací serveru VAPID (RFC 8292) - bez Firebase a bez
 * knihovny. Zpráva je záměrně prázdná: jen probudí service worker prohlížeče, který si obsah
 * vyzvedne u nás (s vlastním ověřením). Obsah tak nejde přes push službu prohlížeče (Google,
 * Apple, Mozilla) a nemusí se šifrovat (RFC 8291) - stačí podepsat VAPID token.
 *
 * Privátní klíč VAPID je PEM klíče EC P-256 v base64 (jeden řádek do .env, SOPS), veřejný se
 * z něj odvodí a dává se prohlížeči při přihlášení k odběru (applicationServerKey).
 * Nový pár: `openssl ecparam -name prime256v1 -genkey -noout | base64 | tr -d '\n'`.
 * Výměna klíče zneplatní všechny odběry - zařízení se musí přihlásit znovu.
 *
 * Služba fancyAdmin.webPushSender, klíč v konfiguraci fancyadmin: webPush (docs/tablets.md).
 */
class WebPushSender
{
	/** Push služby prohlížečů - jinam server nic neposílá (adresu odběru posílá prohlížeč, SSRF) */
	private const array ALLOWED_HOSTS = [
		'fcm.googleapis.com',
		'.push.apple.com',
		'.push.services.mozilla.com',
		'.notify.windows.com',
	];

	/** Jak dlouho push služba drží zprávu pro nedostupné zařízení (s) */
	private const int TTL = 300;

	private ?\OpenSSLAsymmetricKey $key = null;

	public function __construct(
		/** PEM privátního klíče VAPID v base64, '' = Web Push není nastavený */
		#[\SensitiveParameter]
		private readonly string $privateKey,
		/** Kontakt na provozovatele pro push služby (mailto: nebo https:) */
		private readonly string $subject,
	) {
	}

	public function isConfigured(): bool
	{
		return $this->privateKey !== '';
	}

	/** Veřejný klíč VAPID pro PushManager.subscribe() - nekomprimovaný bod P-256 v base64url */
	public function getPublicKey(): string
	{
		$ec = openssl_pkey_get_details($this->getKey())['ec'] ?? throw new \LogicException('Klíč VAPID není klíč EC.');

		return self::base64Url("\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
	}

	/** Adresa odběru z prohlížeče - jen https na známou push službu */
	public static function isValidEndpoint(string $endpoint): bool
	{
		if (strlen($endpoint) > 2048 || !str_starts_with($endpoint, 'https://')) {
			return false;
		}
		$host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
		foreach (self::ALLOWED_HOSTS as $allowed) {
			if (str_starts_with($allowed, '.') ? str_ends_with($host, $allowed) : $host === $allowed) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prázdná push zpráva s nejvyšší naléhavostí (probudí i uspané zařízení).
	 *
	 * @return bool false = odběr už neplatí (odvolané notifikace, odinstalovaný prohlížeč)
	 * @throws WebPushException
	 */
	public function send(string $endpoint): bool
	{
		if (!self::isValidEndpoint($endpoint)) {
			throw new WebPushException('Neplatná adresa odběru Web Push.');
		}

		$curl = curl_init($endpoint);
		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => '',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_HTTPHEADER => [
				'TTL: ' . self::TTL,
				'Urgency: high',
				'Authorization: vapid t=' . $this->createJwt($endpoint) . ', k=' . $this->getPublicKey(),
				'Content-Length: 0',
			],
		]);
		$response = curl_exec($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		if ($response === false) {
			throw new WebPushException('Web Push se nepodařilo odeslat: ' . curl_error($curl), curl_errno($curl));
		}
		if ($status === 404 || $status === 410) {
			return false;
		}
		if ($status < 200 || $status >= 300) {
			$e = new WebPushException('Push služba zprávu odmítla: ' . mb_substr((string) $response, 0, 200));
			$e->httpStatus = $status;
			throw $e;
		}

		return true;
	}

	/** Token VAPID (JWT ES256) pro push službu z adresy odběru, platí 12 h */
	public function createJwt(string $endpoint, int $now = 0): string
	{
		$url = parse_url($endpoint);
		$header = self::base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
		$claims = self::base64Url((string) json_encode([
			'aud' => ($url['scheme'] ?? 'https') . '://' . ($url['host'] ?? ''),
			'exp' => ($now ?: time()) + 12 * 3600,
			'sub' => $this->subject,
		], JSON_UNESCAPED_SLASHES));
		if (!openssl_sign($header . '.' . $claims, $der, $this->getKey(), OPENSSL_ALGO_SHA256)) {
			throw new \RuntimeException('Token VAPID se nepodařilo podepsat.');
		}

		return $header . '.' . $claims . '.' . self::base64Url(self::derToRawSignature($der));
	}

	/** openssl podepisuje ve formátu DER (ASN.1 SEQUENCE dvou INTEGER), JWT chce r || s po 32 bajtech */
	public static function derToRawSignature(string $der): string
	{
		$offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
		$raw = '';
		for ($i = 0; $i < 2; $i++) {
			$length = ord($der[$offset + 1]);
			$integer = ltrim(substr($der, $offset + 2, $length), "\0");
			$raw .= str_pad($integer, 32, "\0", STR_PAD_LEFT);
			$offset += 2 + $length;
		}

		return $raw;
	}

	public static function base64Url(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private function getKey(): \OpenSSLAsymmetricKey
	{
		return $this->key ??= openssl_pkey_get_private((string) base64_decode($this->privateKey, true))
			?: throw new \RuntimeException('Privátní klíč VAPID (fancyadmin: webPush: vapidPrivateKey) není platný PEM v base64.');
	}
}
