<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use DateTimeImmutable;

interface IdentityInterface extends DoctrineAuthenticatorIdentity, IEntity
{
	// Identifier
	public function getId(): ?int;

	// CreatedAt
	public function getCreatedAt(): DateTimeImmutable;
	public function setCreatedAt(DateTimeImmutable $createdAt): self;

	// UpdatedAt
	public function getUpdatedAt(): DateTimeImmutable;
	public function setUpdatedAt(DateTimeImmutable $updatedAt): static;

	// CreatedByNullable
	public function getCreatedBy(): ?IdentityInterface;
	public function setCreatedBy(?IdentityInterface $createdBy): static;

	// UpdatedBy
	public function getUpdatedBy(): ?IdentityInterface;
	public function setUpdatedBy(?IdentityInterface $updatedBy): static;

	// Basic identity
	public function getPassword(): ?string;
	public function setPassword(?string $password): self;

	public function getFirstName(): string;
	public function setFirstName(?string $firstName): self;

	public function getLastName(): string;
	public function setLastName(?string $lastName): self;

	public function getEmail(): string;
	public function setEmail(?string $email): self;

	public function getPhoneNumber(): ?string;
	public function setPhoneNumber(?string $phoneNumber): self;

	public function getFullName(): string;

	// Auth
	public function getAuthObjectId(): string;

	public function getAuthToken(): ?string;
	public function setAuthToken(string $token): void;

	public function isAllowed(string $aclResource): bool;

	public function isAdmin(): bool;

	// Auth metadata
	public function getAuthMetadata(): array;
	public function setAuthMetadata(array $metadata): void;
}
