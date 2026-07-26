<?php

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Nette\Security\Resource;

trait ProfileTrait
{
	use IsActive;
	use CreatedAt;
	use CreatedByNullable;
	use UpdatedAt;
	use UpdatedBy;

	#[ManyToOne(targetEntity: 'Identity', inversedBy: 'profiles')]
	#[JoinColumn(nullable: false)]
	protected Identity $identity;

	#[ManyToOne(targetEntity: 'Account', inversedBy: 'profiles')]
	#[JoinColumn(nullable: false)]
	protected Account $account;

	#[ManyToMany(targetEntity: 'AclRole')]
	#[JoinColumn(onDelete: "CASCADE")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	#[LoggableProperty]
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
		$identity->addProfile($this);
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

	public function isAllowed(Resource $resource): bool
	{
		foreach ($this->getRoles() as $_role) {
			if ($_role->getIsAdmin()) {
				return true;
			}

			if (array_any($_role->getResources(), fn($_resource) => $_resource->getName() === $resource->getResourceId())) {
				return true;
			}
		}

		return false;
	}

	public function getAccount(): Account
	{
		return $this->account;
	}

	public function setAccount(Account $account): static
	{
		$this->account = $account;
		return $this;
	}
}