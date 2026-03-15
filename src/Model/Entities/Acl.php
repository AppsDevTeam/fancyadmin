<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;

interface Acl extends Entity
{
	public function getId(): ?int;

	public function getRole(): AclRole;
	public function setRole(AclRole $role): static;

	public function getResource(): AclResource;
	public function setResource(AclResource $resource): static;

	public function getIsActive(): bool;
	public function setIsActive(bool $isActive): static;
}
