<?php

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\Model\Entities\Traits\HasIdentity;
use ADT\FancyAdmin\Model\Entities\Traits\IsActiveInterface;
use Nette\Security\Resource;

interface Profile extends IsActiveInterface, Entity, HasIdentity
{
	public function getIdentity(): Identity;
	public function setIdentity(Identity $identity): static;

	/** @return AclRole[] */
	public function getRoles(): array;
	public function addRole(AclRole $role): static;

	public function isAllowed(Resource $resource): bool;

	public function getAccount(): Account;
	public function setAccount(Account $account): static;
}