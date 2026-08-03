<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use DateTimeImmutable;

interface Passkey extends Entity
{
	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;

	public function getName(): string;
	public function setName(string $name): static;

	public function getCredentialId(): string;
	public function setCredentialId(string $credentialId): static;

	public function getPublicKey(): string;
	public function setPublicKey(string $publicKey): static;

	public function getSignCount(): int;
	public function setSignCount(int $signCount): static;

	public function getAaguid(): ?string;
	public function setAaguid(?string $aaguid): static;

	public function getTransports(): ?array;
	public function setTransports(?array $transports): static;

	public function getBackupEligible(): ?bool;
	public function setBackupEligible(?bool $backupEligible): static;

	public function getBackupState(): ?bool;
	public function setBackupState(?bool $backupState): static;

	// CreatedAt
	public function getCreatedAt(): DateTimeImmutable;
	public function setCreatedAt(DateTimeImmutable $createdAt): static;

	public function getLastUsedAt(): ?DateTimeImmutable;
	public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): static;
}
