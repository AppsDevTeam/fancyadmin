<?php

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\IsActiveInterface;

interface Profile extends IsActiveInterface
{
	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;

	/** @return AclRole[] */
	public function getRoles(): array;
	public function addRole(AclRole $role): static;

	public function isAllowed(string $resource): bool;
	public function isAllowedContext(string $context): bool;

	public function getAccount(): Account;
	public function setAccount(Account $account): void;
}