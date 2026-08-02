<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Security\SecurityUser;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Nette\Application\Response;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;

final class RequestLogger
{
	public static bool $logResponse = false;
	public static ?int $apiKeyId = null;

	/**
	 * Klíče (case-insensitive), jejichž hodnota se v logu nahradí `***`.
	 * Porovnává se jako substring, takže např. 'password' pokryje i 'passwordConfirm'.
	 *
	 * @var list<string>
	 */
	public static array $sensitiveKeys = [
		'password',
		'passwd',
		'secret',
		'token',
		'authorization',
		'api_key',
		'apikey',
		'pin',
	];

	private const string MASK = '***';

	/** @var array<string, mixed> Vlastní projektové sloupce pro tabulku `request_log` */
	private static array $extraLogData = [];

	public function __construct(
		private readonly array $dbParams,
		private readonly SecurityUser $securityUser,
	) {
	}

	/**
	 * Přidá vlastní sloupec do logu requestu (tabulka `request_log`).
	 *
	 * Volej kdykoliv během zpracování requestu (typicky v presenteru), např.:
	 *   RequestLogger::addValue('device_id', $deviceId);
	 *
	 * Systémové sloupce (created_at, method, url, ip, code, response_time,
	 * identity_id, api_key_id) nelze přepsat – slouží pouze k PŘIDÁVÁNÍ.
	 */
	public static function addValue(string $column, mixed $value): void
	{
		self::$extraLogData[$column] = $value;
	}

	public function logRequest(Presenter $presenter, Response $response): void
	{
		if (!self::$apiKeyId && !$this->securityUser->isLoggedIn()) {
			return;
		}

		try {
			$this->doLogRequest($presenter, $response);
		} catch (Throwable $e) {
			Debugger::log('RequestLogger selhal: ' . $e->getMessage(), ILogger::CRITICAL);
		}
	}

	/**
	 * @throws JsonException
	 * @throws Exception
	 * @throws \Exception
	 */
	private function doLogRequest(Presenter $presenter, Response $response): void
	{
		if (json_validate($presenter->getHttpRequest()->getRawBody())) {
			$raw_data_text = null;
			$raw_data_json = Json::decode($presenter->getHttpRequest()->getRawBody(), forceArrays: true);
		} else {
			$raw_data_json = null;
			$raw_data_text = $presenter->getHttpRequest()->getRawBody();
		}

		if (self::$logResponse) {
			if (!$response instanceof FileResponse) {
				ob_start();
				$response->send($presenter->getHttpRequest(), $presenter->getHttpResponse());
				$response = ob_get_clean();
				if (json_validate($response)) {
					$response_text = null;
					$response_json = Json::decode($response, forceArrays: true);
				} else {
					$response_text = $response;
					$response_json = null;
				}
			} else {
				$response_text = null;
				$response_json = null;
			}
		} else {
			$response_text = null;
			$response_json = null;
		}

		$headers = $presenter->getHttpRequest()->getHeaders();
		unset($headers['authorization']);
		unset($headers['x-api-key']);

		$connection = DriverManager::getConnection($this->dbParams);

		// Systémové sloupce mají díky `+` vždy přednost – extra data (viz addValue())
		// mohou pouze PŘIDÁVAT vlastní sloupce, ne přepsat defaultní logování.
		$connection->insert('request_log', [
			'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s.u'),
			'method' => $presenter->getHttpRequest()->getMethod(),
			'url' => $presenter->getHttpRequest()->getUrl()->getBaseUrl() . ltrim($presenter->getHttpRequest()->getUrl()->getPath(), '/'),
			'ip' => $presenter->getHttpRequest()->getRemoteAddress(),
			'code' => $presenter->getHttpResponse()->getCode(),
			'response_time' => (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
			'identity_id' => $this->securityUser->isLoggedIn() ? $this->securityUser->getId() : null,
			'api_key_id' => self::$apiKeyId,
		] + self::$extraLogData);

		$requestLogId = $connection->lastInsertId();

		$connection->insert('request_log_body', [
			'request_log_id' => $requestLogId,
			'headers' => $headers ? Json::encode($this->normalizeData($headers)) : null,
			'params' => $_GET ? Json::encode($this->normalizeData($_GET)) : null,
			'post_data' => $_POST ? Json::encode($this->normalizeData($_POST)) : null,
			'raw_data_json' => $raw_data_json ? Json::encode($this->normalizeData($raw_data_json)) : null,
			'raw_data_text' => $this->normalizeData($raw_data_text),
			'response_json' => $response_json ? Json::encode($this->normalizeData($response_json)) : null,
			'response_text' => $this->normalizeData($response_text),
		]);
	}

	private function normalizeData($data)
	{
		if (empty($data)) {
			return null;
		}

		if (is_array($data)) {
			$output = [];
			foreach ($data as $key => $value) {
				$normalizedKey = is_string($key) ? $this->normalizeString($key) : $key;
				$output[$normalizedKey] = $this->isSensitiveKey($normalizedKey)
					? self::MASK
					: $this->normalizeData($value); // Rekurze
			}
			return $output;
		}

		if (is_string($data)) {
			if (strlen($data) >= 255 && $this->isBase64Encoded($data)) {
				return 'md5:' . md5($data);
			}
			return $this->normalizeString($data);
		}

		// Ostatní případy (např. scalar typy) – vracíme beze změny
		return $data;
	}

	/**
	 * Je klíč citlivý? (case-insensitive substring match proti self::$sensitiveKeys)
	 */
	private function isSensitiveKey(int|string $key): bool
	{
		if (!is_string($key)) {
			return false;
		}

		$key = mb_strtolower($key);
		foreach (self::$sensitiveKeys as $sensitiveKey) {
			if (str_contains($key, mb_strtolower($sensitiveKey))) {
				return true;
			}
		}

		return false;
	}

	private function normalizeString(string $value): string
	{
		// Neplatné UTF-8 (např. z útočných requestů) nahradíme náhradním znakem,
		// aby šlo hodnotu uložit i serializovat do JSONu bez výjimky
		if (!mb_check_encoding($value, 'UTF-8')) {
			$value = mb_scrub($value, 'UTF-8');
		}
		return $this->removeControlCharacters($value);
	}

	private function isBase64Encoded(string $string): bool
	{
		$decoded = base64_decode($string, true);

		if ($decoded !== false) {
			if (base64_encode($decoded) === $string) {
				return true;
			}
		}

		return false;
	}

	private function removeControlCharacters(string $input): string
	{
		// Odebereme všechny znaky s ASCII hodnotou < 32 kromě \n (10), \r (13) a \t (9)
		return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $input);
	}
}
