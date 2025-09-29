<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\FancyAdmin\Model\Entities\Traits\IsActiveInterface;
use DateTimeImmutable;

interface Identity extends DoctrineAuthenticatorIdentity, IsActiveInterface
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
	public function getCreatedBy(): ?Identity;
	public function setCreatedBy(?Identity $createdBy): static;

	// UpdatedBy
	public function getUpdatedBy(): ?Identity;
	public function setUpdatedBy(?Identity $updatedBy): static;

	// Basic identity
	public function getPassword(): ?string;
	public function setPassword(?string $password): static;

	public function getFirstName(): string;
	public function setFirstName(?string $firstName): static;

	public function getLastName(): string;
	public function setLastName(?string $lastName): static;

	public function getEmail(): ?string;
	public function setEmail(?string $email): static;

	public function getUsername(): ?string;
	public function setUsername(?string $username): static;

	public function getPhoneNumber(): ?string;
	public function setPhoneNumber(?string $phoneNumber): static;

	public function getSelectedAccount(): ?Account;
	public function setSelectedAccount(?Account $selectedAccount): static;

	public function getFullName(): string;
	public function getGravatar(): string;

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
