<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Base\BaseEntity;
use Contributte\Translation\Exceptions\InvalidArgument;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Nette\Security\Role;

#[ORM\Entity]
class AclRole extends BaseEntity implements Role
{
	use Identifier;

	const int ID_SALESPERSON = 4;

	const array TYPES_TRANSLATABLE = [
		'admin' => 'app.appGeneral.model.enum.aclRole.admin',
		'owner' => 'app.appGeneral.model.enum.aclRole.owner',
		'branch leader' => 'app.appGeneral.model.enum.aclRole.branchLeader',
		'salesperson' => 'app.appGeneral.model.enum.aclRole.salesPerson',
		'supervision' => 'app.appGeneral.model.enum.aclRole.supervision',
	];

	#[ORM\Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\ManyToMany(targetEntity: AclResource::class, inversedBy: 'roles', cascade: ["persist"])]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $resources;

	#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'roles')]
	protected Collection $users;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isAdmin = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isCustomer = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isVisitor = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isLimitedToBranch = false;

	public function __construct()
	{
		$this->users = new ArrayCollection();
		$this->resources = new ArrayCollection();
	}

	public function getRoleId(): string
	{
		return $this->name;
	}

	public function addUser(User $user): static
	{
		$this->users->add($user);
		return $this;
	}

	public function addResource(AclResource $resource): static
	{
		if ($this->resources->contains($resource)) {
			return $this;
		}
		$this->resources->add($resource);
		$resource->addRole($this);
		return $this;
	}

	public function removeResource(AclResource $resource): static
	{
		if (!$this->resources->contains($resource)) {
			return $this;
		}
		$this->resources->removeElement($resource);
		$resource->removeRole($this);
		return $this;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): string
	{
		return $this->translate(static::TYPES_TRANSLATABLE[$this->name]);
	}

	/**
	 * @return AclResource[]
	 */
	public function getResources(): array
	{
		return $this->resources->toArray();
	}

	/**
	 * @return User[]
	 */
	public function getUsers(): array
	{
		return $this->users->toArray();
	}

	public function getIsCustomer(): bool
	{
		return $this->isCustomer;
	}

	public function setIsCustomer(bool $isCustomer): self
	{
		$this->isCustomer = $isCustomer;
		return $this;
	}

	public function getIsAdmin(): bool
	{
		return $this->isAdmin;
	}

	public function setIsAdmin(bool $isAdmin): static
	{
		$this->isAdmin = $isAdmin;
		return $this;
	}

	public function getIsLimitedToBranch(): bool
	{
		return $this->isLimitedToBranch;
	}

	public function setIsLimitedToBranch(bool $isLimitedToBranch): static
	{
		$this->isLimitedToBranch = $isLimitedToBranch;
		return $this;
	}

	public function isAllowed(string $aclResource): bool
	{
		return array_any($this->getResources(), fn(AclResource $_resource) => $_resource->getName() === $aclResource);
	}

	public function isVisitor(): bool
	{
		return $this->isVisitor;
	}

	public function setIsVisitor(bool $isVisitor): AclRole
	{
		$this->isVisitor = $isVisitor;
		return $this;
	}
}
