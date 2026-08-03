<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait PasskeyTrait
{
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: 'Identity', inversedBy: 'passkeys')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	protected Identity $identity;

	#[ORM\Column(length: 64, nullable: false)]
	#[LoggableProperty]
	protected string $name;

	/** Raw binary credential ID (VARBINARY(255)); DBAL binary type může vracet stream, getter normalizuje. */
	#[ORM\Column(type: 'binary', length: 255, unique: true, nullable: false)]
	protected mixed $credentialId;

	/** Veřejný klíč v PEM formátu (výstup lbuchs/webauthn processCreate) */
	#[ORM\Column(type: 'text', nullable: false)]
	protected string $publicKey;

	#[ORM\Column(type: 'integer', nullable: false, options: ['unsigned' => true, 'default' => 0])]
	protected int $signCount = 0;

	/** Raw binary AAGUID autentikátoru (BINARY(16)) */
	#[ORM\Column(type: 'binary', length: 16, nullable: true, options: ['fixed' => true])]
	protected mixed $aaguid = null;

	#[ORM\Column(type: 'json', nullable: true)]
	protected ?array $transports = null;

	#[ORM\Column(nullable: true)]
	protected ?bool $backupEligible = null;

	#[ORM\Column(nullable: true)]
	protected ?bool $backupState = null;

	#[ORM\Column(nullable: true)]
	protected ?DateTimeImmutable $lastUsedAt = null;

	public function getIdentity(): Identity
	{
		return $this->identity;
	}

	public function setIdentity(Identity $identity): static
	{
		$this->identity = $identity;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getCredentialId(): string
	{
		return self::binaryColumnToString($this->credentialId);
	}

	public function setCredentialId(string $credentialId): static
	{
		$this->credentialId = $credentialId;
		return $this;
	}

	public function getPublicKey(): string
	{
		return $this->publicKey;
	}

	public function setPublicKey(string $publicKey): static
	{
		$this->publicKey = $publicKey;
		return $this;
	}

	public function getSignCount(): int
	{
		return $this->signCount;
	}

	public function setSignCount(int $signCount): static
	{
		$this->signCount = $signCount;
		return $this;
	}

	public function getAaguid(): ?string
	{
		return $this->aaguid === null ? null : self::binaryColumnToString($this->aaguid);
	}

	public function setAaguid(?string $aaguid): static
	{
		$this->aaguid = $aaguid;
		return $this;
	}

	public function getTransports(): ?array
	{
		return $this->transports;
	}

	public function setTransports(?array $transports): static
	{
		$this->transports = $transports;
		return $this;
	}

	public function getBackupEligible(): ?bool
	{
		return $this->backupEligible;
	}

	public function setBackupEligible(?bool $backupEligible): static
	{
		$this->backupEligible = $backupEligible;
		return $this;
	}

	public function getBackupState(): ?bool
	{
		return $this->backupState;
	}

	public function setBackupState(?bool $backupState): static
	{
		$this->backupState = $backupState;
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

	/** DBAL typ binary hydratuje podle verze DBAL buď string, nebo stream */
	private static function binaryColumnToString(mixed $value): string
	{
		if (is_resource($value)) {
			rewind($value);
			return (string) stream_get_contents($value);
		}
		return (string) $value;
	}
}
