<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model;

use ADT\LogSanitizer\SensitiveDataSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Throwable;

/**
 * Zapisuje záznamy o volání cizích rozhraní, kde aplikace vystupuje jako klient.
 *
 * VLASTNÍ SPOJENÍ, ne to od EntityManageru. Volání ven typicky probíhá uvnitř otevřené
 * ORM transakce a ta se může rozpadnout právě proto, co protistrana odpověděla - záznam
 * o komunikaci by se rollbackem ztratil přesně v případě, kvůli kterému se loguje.
 * Zápis přes DBAL (ne přes entitu) navíc nesahá na rozpracovaný flush ani nezavře
 * EntityManager, kdyby log selhal.
 *
 * Selhání zápisu se polyká: logování nesmí shodit operaci, o které vypovídá.
 *
 * Obsah se sanitizuje - do cizího rozhraní i zpátky můžou téct údaje, které se do logu
 * uložit nesmí (viz SensitiveDataSanitizer).
 */
final class ApiLogger
{
	private ?Connection $connection = null;

	/**
	 * @param array<string, mixed> $dbParams např. @nettrine.dbal.connections.default.connection::getParams()
	 * @param string $table tabulka, do které se píše
	 */
	public function __construct(
		private readonly array $dbParams,
		private readonly SensitiveDataSanitizer $sanitizer,
		private readonly string $table = 'api_log',
	) {
	}

	/**
	 * @param string $type které rozhraní se volalo - výčet si určuje projekt (typicky backed enum)
	 * @param array<string, mixed> $extraValues vlastní sloupce projektu, např. vazba na doklad
	 */
	public function log(
		string $type,
		?string $environment,
		?string $endpointUrl,
		?string $request,
		?string $response,
		?int $httpStatusCode,
		?int $durationMs,
		?int $accountId = null,
		?string $errorMessage = null,
		array $extraValues = [],
	): void {
		try {
			// systemove sloupce maji pres `+` prednost: extra data smi jen pridavat
			$this->getConnection()->insert($this->table, [
				'type' => $type,
				'environment' => $environment,
				'endpoint_url' => $endpointUrl,
				'request' => $this->sanitize($request),
				'response' => $this->sanitize($response),
				'http_status_code' => $httpStatusCode,
				'error_message' => $this->sanitize($errorMessage),
				'duration_ms' => $durationMs,
				'account_id' => $accountId,
				// UTC, at' se zaznam da srovnat s ostatnimi logy i po odvozu do jine databaze
				'created_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
			] + $extraValues);
		} catch (Throwable) {
			// zamerne: vypadek logovani nesmi shodit samotne volani
		}
	}

	private function sanitize(?string $value): ?string
	{
		return $value === null ? null : $this->sanitizer->sanitize($value);
	}

	private function getConnection(): Connection
	{
		return $this->connection ??= DriverManager::getConnection($this->dbParams);
	}
}
