<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineAuthenticator\DoctrineAuthenticatorIdentity;
use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\Traits\HasIdentity;
use ADT\FancyAdmin\Model\Entities\Traits\IsActiveInterface;
use DateTimeImmutable;
use Nette\Security\Resource;

interface Identity extends DoctrineAuthenticatorIdentity, IsActiveInterface, Entity, HasIdentity
{
	// CreatedAt
	public function getCreatedAt(): DateTimeImmutable;
	public function setCreatedAt(DateTimeImmutable $createdAt): static;

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

	public function getFirstName(): ?string;
	public function setFirstName(?string $firstName): static;

	public function getLastName(): ?string;
	public function setLastName(?string $lastName): static;

	public function getEmail(): ?string;
	public function setEmail(?string $email): static;

	public function getContext(): ?string;
	public function setContext(?string $context): static;

	public function getUsername(): ?string;
	public function setUsername(?string $username): static;

	public function getPhoneNumber(): ?string;
	public function setPhoneNumber(?string $phoneNumber): static;

	public function getSelectedAccount(): ?Account;
	public function setSelectedAccount(?Account $selectedAccount): static;

	public function getSso(): ?Sso;
	public function setSso(?Sso $sso): static;

	public function getPasskeyUserHandle(): ?string;
	public function setPasskeyUserHandle(?string $passkeyUserHandle): static;

	public function getFullName(): string;
	public function getGravatar(): string;
	public function getAccounts(): array;
	public function getSubaccounts(): array;

	// Auth
	public function isAllowed(Resource $aclResource): bool;

	public function isAdmin(): bool;
	
	public function addRole(AclRole $role): static;

	/**
	 * @return Profile[]
	 */
	public function getProfiles(): array;
	public function addProfile(Profile $profile): static;

	// Anonymized
	public function getAnonymizedAt(): ?DateTimeImmutable;
	public function setAnonymizedAt(?DateTimeImmutable $anonymizedAt): static;

	public function getAnonymizedBy(): ?Identity;
	public function setAnonymizedBy(?Identity $anonymizedBy): static;
}
