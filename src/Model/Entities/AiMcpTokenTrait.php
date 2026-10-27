<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAnonymization\AnonymizationType;
use ADT\DoctrineAnonymization\Attributes\Anonymize;
use ADT\DoctrineAnonymization\Attributes\Description;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait AiMcpTokenTrait
{
	use CreatedAt;

	#[ORM\Column(nullable: false)]
	#[Description('Name of the token given by its owner (e.g. the client it is used in).')]
	protected string $name;

	/** SHA-256 otisk tokenu (ApiKeyHasher), čitelný token se ukáže jen jednou při vytvoření. */
	#[ORM\Column(unique: true, nullable: true)]
	#[Anonymize(AnonymizationType::SECRET)]
	#[Description('SHA-256 hash of the token; the readable token is never stored.')]
	protected ?string $tokenHash = null;

	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	#[Description('User (identity) the token belongs to; MCP clients act with their permissions.')]
	protected Identity $identity;

	#[ORM\Column(nullable: true)]
	#[Description('Expiration of the token; null = does not expire.')]
	protected ?DateTimeImmutable $expiresAt = null;

	#[ORM\Column(nullable: true)]
	#[Description('Last successful request with the token (updated at most once a minute).')]
	protected ?DateTimeImmutable $lastUsedAt = null;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getTokenHash(): ?string
	{
		return $this->tokenHash;
	}

	public function setTokenHash(?string $tokenHash): static
	{
		$this->tokenHash = $tokenHash;
		return $this;
	}

	public function getIdentity(): Identity
	{
		return $this->identity;
	}

	public function setIdentity(Identity $identity): static
	{
		$this->identity = $identity;
		return $this;
	}

	public function getExpiresAt(): ?DateTimeImmutable
	{
		return $this->expiresAt;
	}

	public function setExpiresAt(?DateTimeImmutable $expiresAt): static
	{
		$this->expiresAt = $expiresAt;
		return $this;
	}

	public function getLastUsedAt(): ?DateTimeImmutable
	{
		return $this->lastUsedAt;
	}

	public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): static
	{
		$this->lastUsedAt = $lastUsedAt;
		return $this;
	}

	public function isExpired(?DateTimeImmutable $now = null): bool
	{
		return $this->expiresAt !== null && $this->expiresAt <= ($now ?? new DateTimeImmutable());
	}
}
