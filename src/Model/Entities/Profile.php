<?php

namespace ADT\FancyAdmin\Model\Entities;

interface Profile
{
	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;
	/** @return AclRole[] */
	public function getRoles(): array;
	public function addRole(AclRole $role): static;
	public function isAllowed(string $resource): bool;
	public function isAllowedContext(string $context): bool;
	public function getIsActive(): bool;
	public function setIsActive(bool $isActive): static;
}