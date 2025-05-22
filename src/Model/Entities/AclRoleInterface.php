<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Nette\Security\Role;

interface AclRoleInterface extends Role
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
	 * @return AclResourceInterface[]
	 */
	public function getResources(): array;
	public function addResource(AclResourceInterface $resource): static;
	public function removeResource(AclResourceInterface $resource): static;
}
