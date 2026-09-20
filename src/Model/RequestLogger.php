<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Security\SecurityUser;
use ADT\LogSanitizer\SensitiveDataSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
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
	/**
	 * Nejhlubší zanoření, které MySQL pustí do sloupce typu `json`; hlubší dokument odmítne
	 * chybou 3157 (The JSON document exceeds the maximum depth). PHP proti tomu parsuje do
	 * 512 úrovní, takže tělo mezi těmito dvěma limity aplikací projde a rozbije se až tady.
	 */
	private const int MAX_JSON_COLUMN_DEPTH = 100;

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
		// Hloubka se hlídá spolu s validitou: co se do `json` sloupce nevejde, uloží se jako
		// text. Dřív takový požadavek shodil celý insert do `request_log_body`, takže se
		// ztratilo tělo i odpověď - a stačilo ho poslat, aby v logu nebyly. Text je horší
		// na dotazování, ale je to pořád celý obsah.
		if (json_validate($presenter->getHttpRequest()->getRawBody(), self::MAX_JSON_COLUMN_DEPTH)) {
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
				if (json_validate($response, self::MAX_JSON_COLUMN_DEPTH)) {
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
		// jeden okamzik pro rodice i telo: retencni mazani je porovnava mezi sebou
		// (telo ma kratsi retenci), takze se nesmi lisit ani o milisekundu
		$createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

		$this->writeLog(
			$connection,
			[
				// UTC - stejne jako audit_log, kvuli korelaci a jednoznacnosti pri
				// prechodu na zimni cas (2:30 nastane dvakrat)
				'created_at' => $createdAt,
				'method' => $presenter->getHttpRequest()->getMethod(),
				'url' => $presenter->getHttpRequest()->getUrl()->getBaseUrl() . ltrim($presenter->getHttpRequest()->getUrl()->getPath(), '/'),
				// delku IP ovlada klient (X-Forwarded-For) - nesmi rozbit insert
				'ip' => mb_substr((string) $presenter->getHttpRequest()->getRemoteAddress(), 0, 45),
				'code' => $presenter->getHttpResponse()->getCode(),
				'response_time' => (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
				'identity_id' => $this->securityUser->isLoggedIn() ? $this->securityUser->getId() : null,
				'api_key_id' => self::$apiKeyId,
			] + self::$extraLogData,
			[
				'created_at' => $createdAt,
				'headers' => $headers ? Json::encode($headers) : null,
				'params' => $_GET ? Json::encode($this->sanitizer->sanitize($_GET)) : null,
				'post_data' => $_POST ? Json::encode($this->sanitizer->sanitize($_POST)) : null,
				'raw_data_json' => $raw_data_json ? Json::encode($this->sanitizer->sanitize($raw_data_json)) : null,
				'raw_data_text' => $raw_data_text === null ? null : $this->sanitizer->sanitize($raw_data_text),
				'response_json' => $response_json ? Json::encode($this->sanitizer->sanitize($response_json)) : null,
				'response_text' => $response_text === null ? null : $this->sanitizer->sanitize($response_text),
			],
		);
	}

	/**
	 * Zapíše hlavičku a tělo požadavku JEDNOU TRANSAKCÍ, i když jsou to dva inserty.
	 *
	 * Mezi zápisem hlavičky a těla je jinak okamžik, kdy rodič už v databázi je a tělo ještě
	 * ne. Odvoz logů (fancyadmin:move-logs) běží souběžně a v tu chvíli mu nic nebrání rodiče
	 * odvézt a ze zdroje smazat - tělo pak spadne na cizím klíči:
	 *
	 *   Cannot add or update a child row: a foreign key constraint fails
	 *   (`request_log_body`, CONSTRAINT `FK_...` FOREIGN KEY (`request_log_id`))
	 *
	 * a z požadavku nezbude ani hlavička, ani tělo. V transakci rodič pro odvoz neexistuje,
	 * dokud není hotové i tělo.
	 *
	 * @param array<string, mixed> $requestLog
	 * @param array<string, mixed> $requestLogBody
	 * @throws Exception
	 */
	private function writeLog(Connection $connection, array $requestLog, array $requestLogBody): void
	{
		$connection->beginTransaction();
		try {
			$connection->insert('request_log', $requestLog);
			$connection->insert('request_log_body', ['request_log_id' => $connection->lastInsertId()] + $requestLogBody);
			$connection->commit();
		} catch (Throwable $e) {
			$connection->rollBack();

			throw $e;
		}
	}

}
