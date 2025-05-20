<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Nette\Security\Role;

interface IAclRole extends Role
{
	// Role interface (Nette)
	public function getRoleId(): string;

	// Identifikátor
	public function getId(): ?int;

	// Název (přeložený)
	public function getName(): string;
	public function setName(string $name): static;

	// Přístupová práva
	public function isAllowed(string $aclResource): bool;

	// Role flags
	public function getIsAdmin(): bool;
	public function setIsAdmin(bool $isAdmin): static;

	public function getIsCustomer(): bool;
	public function setIsCustomer(bool $isCustomer): self;

	public function isVisitor(): bool;
	public function setIsVisitor(bool $isVisitor): static;

	public function getIsLimitedToBranch(): bool;
	public function setIsLimitedToBranch(bool $isLimitedToBranch): static;

	// Uživatele
	/**
	 * @return IUser[]
	 */
	public function getUsers(): array;
	public function addUser(IUser $user): static;

	// Zdroje
	/**
	 * @return IAclResource[]
	 */
	public function getResources(): array;
	public function addResource(IAclResource $resource): static;
	public function removeResource(IAclResource $resource): static;
}
