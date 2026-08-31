<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

/**
 * Jednotny auditni zaznam - jeden append-only stream udalosti pro cely
 * system (exporty, autentizace, vydeje souboru...).
 *
 * Ploche jsou jen sloupce, ktere maji smysl NAPRIC typy udalosti; cokoliv
 * dalsiho je per-typ a patri do payloadu.
 */
interface AuditLog extends Entity
{
	public function getAction(): string;
	public function getCreatedById(): ?string;
	public function getCreatedByLabel(): ?string;
	public function getCreatedBy(): ?array;
	public function getSourceIp(): ?string;
	public function getUserAgent(): ?string;
	public function getCorrelationId(): ?string;
	public function getPayload(): ?array;

	/** vzdy v UTC - viz AuditLogTrait::$createdAt */
	public function getCreatedAtUtc(): DateTimeImmutable;
}
