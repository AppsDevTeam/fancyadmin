<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Nette\Security\Role;

interface AclRole extends Role
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

	// Zdroje
	/**
	 * @return AclResource[]
	 */
	public function getResources(): array;
	public function addResource(AclResource $resource): static;
	public function removeResource(AclResource $resource): static;
}
