<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\AiChat\Mcp\McpTokenTrait;
use ADT\DoctrineAnonymization\Attributes\Description;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sloupce tokenu (name, token_hash, expires_at, last_used_at) a jejich metody mapuje
 * McpTokenTrait z adt/ai-chat; tady zůstává vlastník (identita) a čas vytvoření.
 */
trait AiMcpTokenTrait
{
	use CreatedAt;
	use McpTokenTrait;

	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	#[Description('User (identity) the token belongs to; MCP clients act with their permissions.')]
	protected Identity $identity;

	public function getIdentity(): Identity
	{
		return $this->identity;
	}

	public function setIdentity(Identity $identity): static
	{
		$this->identity = $identity;
		return $this;
	}

	/** McpToken: klient pracuje s právy identity. */
	public function getTokenOwner(): Identity
	{
		return $this->identity;
	}
}
