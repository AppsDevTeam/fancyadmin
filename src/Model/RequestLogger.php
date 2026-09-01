<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use DateTimeImmutable;
use DateTimeZone;
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

	/** @var array<string, mixed> Vlastní projektové sloupce pro tabulku `request_log` */
	private static array $extraLogData = [];

	public function __construct(
		private readonly array $dbParams,
		private readonly SecurityUser $securityUser,
		private readonly SensitiveDataSanitizer $sanitizer,
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

		// sanitizeHeaders() vyhodi nositele pristupu uplne (authorization,
		// x-api-key, cookie...) a zbytek ocisti jako hodnoty
		$headers = $this->sanitizer->sanitizeHeaders($presenter->getHttpRequest()->getHeaders());

		$connection = DriverManager::getConnection($this->dbParams);

		// Systémové sloupce mají díky `+` vždy přednost – extra data (viz addValue())
		// mohou pouze PŘIDÁVAT vlastní sloupce, ne přepsat defaultní logování.
		$connection->insert('request_log', [
			// UTC - stejne jako audit_log, kvuli korelaci a jednoznacnosti pri
			// prechodu na zimni cas (2:30 nastane dvakrat)
			'created_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
			'method' => $presenter->getHttpRequest()->getMethod(),
			'url' => $presenter->getHttpRequest()->getUrl()->getBaseUrl() . ltrim($presenter->getHttpRequest()->getUrl()->getPath(), '/'),
			// delku IP ovlada klient (X-Forwarded-For) - nesmi rozbit insert
			'ip' => mb_substr((string) $presenter->getHttpRequest()->getRemoteAddress(), 0, 45),
			'code' => $presenter->getHttpResponse()->getCode(),
			'response_time' => (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
			'identity_id' => $this->securityUser->isLoggedIn() ? $this->securityUser->getId() : null,
			'api_key_id' => self::$apiKeyId,
		] + self::$extraLogData);

		$requestLogId = $connection->lastInsertId();

		$connection->insert('request_log_body', [
			'request_log_id' => $requestLogId,
			'headers' => $headers ? Json::encode($headers) : null,
			'params' => $_GET ? Json::encode($this->sanitizer->sanitize($_GET)) : null,
			'post_data' => $_POST ? Json::encode($this->sanitizer->sanitize($_POST)) : null,
			'raw_data_json' => $raw_data_json ? Json::encode($this->sanitizer->sanitize($raw_data_json)) : null,
			'raw_data_text' => $raw_data_text === null ? null : $this->sanitizer->sanitize($raw_data_text),
			'response_json' => $response_json ? Json::encode($this->sanitizer->sanitize($response_json)) : null,
			'response_text' => $response_text === null ? null : $this->sanitizer->sanitize($response_text),
		]);
	}

}
