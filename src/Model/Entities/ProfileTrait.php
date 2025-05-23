<?php

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Attributes\IsActive;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;

trait ProfileTrait
{
	use Identifier;
	use IsActive;

	#[ManyToOne(targetEntity: 'Identity', inversedBy: 'profiles')]
	#[JoinColumn(nullable: false)]
	protected Identity $identity;

	#[ManyToMany(targetEntity: 'AclRole')]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $roles;

	public function __construct()
	{
		$this->roles = new ArrayCollection();
	}

	public function getIdentity(): Identity
	{
		return $this->identity;
	}

	public function setIdentity(Identity $identity): static
	{
		$this->identity = $identity;
		return $this;
	}

	/**
	 * @return AclRole[]
	 */
	public function getRoles(): array
	{
		return $this->roles->toArray();
	}

	public function addRole(AclRole $role): static
	{
		if ($this->roles->contains($role)) {
			return $this;
		}
		$this->roles->add($role);
		return $this;
	}

	public function isAllowed(string $resource): bool
	{
		foreach ($this->getRoles() as $_role) {
			if ($_role->getIsAdmin()) {
				return true;
			}

			if (array_any($_role->getResources(), fn($_resource) => $_resource->getName() === $resource)) {
				return true;
			}
		}

		return false;
	}

	public function isAllowedContext(string $context): bool
	{
		if ($this->isAllowed($context)) {
			return true;
		}

		return false;
	}
}