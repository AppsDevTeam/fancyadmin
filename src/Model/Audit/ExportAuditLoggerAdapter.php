<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Audit;

use ADT\Exporter\Model\Service\ExportAuditLogger;
use DateTimeImmutable;

/**
 * Naplni rozhrani, ktere si deklaruje adt/exporter, a nasmeruje auditni
 * udalosti exportu do jednotne tabulky audit_log.
 *
 * adt/exporter je volitelna zavislost (viz composer.json "suggest"), takze
 * extension tuto sluzbu registruje jen tehdy, kdyz je rozhrani k dispozici.
 */
final readonly class ExportAuditLoggerAdapter implements ExportAuditLogger
{
	public function __construct(private AuditLogger $auditLogger)
	{
	}

	public function log(
		string $action,
		DateTimeImmutable $createdAt,
		?string $correlationId,
		array $actor,
		array $payload,
		bool $detached = false,
	): void {
		$this->auditLogger->log($action, $createdAt, $correlationId, $actor, $payload, $detached);
	}
}
