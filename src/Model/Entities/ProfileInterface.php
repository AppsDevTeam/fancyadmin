<?php

namespace ADT\FancyAdmin\Model\Entities;

interface ProfileInterface
{
	public function getIdentity(): IdentityInterface;
	public function setIdentity(IdentityInterface $identity): static;
	/** @return AclRoleInterface[] */
	public function getRoles(): array;
	public function addRole(AclRoleInterface $role): static;
	public function isAllowed(string $resource): bool;
	public function isAllowedContext(string $context): bool;
}