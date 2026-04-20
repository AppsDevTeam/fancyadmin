<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use Nette\Security\Resource;
use Nette\Security\Role;

interface AclRole extends Role, Entity
{
	// AclRole interface (Nette)
	public function getRoleId(): string;

	// Identifikátor
	public function getId(): ?int;

	// Název (přeložený)
	public function getName(): string;
	public function setName(string $name): static;

	// Přístupová práva
	public function isAllowed(Resource $aclResource): bool;

	// Kontext
	public function getContext(): ?string;
	public function setContext(?string $context): static;

	// AclRole flags
	public function getIsAdmin(): bool;
	public function setIsAdmin(bool $isAdmin): static;

	// SSO
	public function getSso(): ?Sso;
	public function setSso(?Sso $sso): static;

	// Zdroje
	/**
	 * @return AclResource[]
	 */
	public function getResources(): array;
}
