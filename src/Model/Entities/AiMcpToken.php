<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

/**
 * Přístupový token k MCP serveru AI asistenta (adt/ai-chat, Mcp\McpServer). Patří
 * jedné identitě - MCP klient (Claude Code, Claude Desktop, ...) pracuje s jejími
 * právy, viz AiMcpEndpoint.
 */
interface AiMcpToken extends Entity
{
	public function getName(): string;
	public function setName(string $name): static;

	public function getTokenHash(): ?string;
	public function setTokenHash(?string $tokenHash): static;

	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;

	public function getCreatedAt(): DateTimeImmutable;

	public function getExpiresAt(): ?DateTimeImmutable;
	public function setExpiresAt(?DateTimeImmutable $expiresAt): static;

	public function getLastUsedAt(): ?DateTimeImmutable;
	public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): static;

	public function isExpired(?DateTimeImmutable $now = null): bool;
}
