<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Audit;

use ADT\DoctrineAuthenticator\AuthAuditLogger;
use DateTimeImmutable;

/**
 * Naplni rozhrani, ktere si deklaruje adt/doctrine-authenticator, a nasmeruje
 * autentizacni udalosti do jednotne tabulky audit_log.
 *
 * Adapter existuje proto, aby authenticator nemusel zaviset na fancyadminu -
 * zavislost jde opacnym smerem, nez by clovek cekal, a proto to funguje.
 */
final readonly class AuthAuditLoggerAdapter implements AuthAuditLogger
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
