<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\FancyAdmin\Model\Entities\Attributes\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\UpdatedBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToMany;

/** @mixin IUser */
#[ORM\Entity]
trait User
{
	use Identifier;
	use CreatedAt;
	use UpdatedAt;
	use CreatedByNullable;
	use UpdatedBy;

	#[ORM\Column(nullable:false)]
	protected string $firstName;

	#[ORM\Column(nullable:false)]
	protected string $lastName;

	#[ORM\Column(unique: true, nullable:false)]
	protected string $email;

	#[ORM\Column(unique: true, nullable:true)]
	protected ?string $phoneNumber;

	#[ORM\Column(nullable: true)]
	protected ?string $password = null;

	#[ORM\Column(nullable: false, options: ["default" => 1])]
	protected bool $isActive = true;

	protected string $authToken;

	#[ManyToMany(targetEntity: 'AclRole', inversedBy: 'users', cascade: ["persist"])]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $roles;

	public function __construct()
	{
		$this->roles = new ArrayCollection();
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function setPassword(?string $password): self
	{
		$this->password = $password;
		return $this;
	}

	public function getFirstName(): string
	{
		return $this->firstName;
	}

	public function setFirstName(?string $firstName): self
	{
		$this->firstName = $firstName;
		return $this;
	}

	public function getLastName(): string
	{
		return $this->lastName;
	}

	public function setLastName(?string $lastName): self
	{
		$this->lastName = $lastName;
		return $this;
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		return $this;
	}

	public function getPhoneNumber(): ?string
	{
		return $this->phoneNumber;
	}

	public function setPhoneNumber(?string $phoneNumber): self
	{
		$this->phoneNumber = $phoneNumber;
		return $this;
	}

	public function getIsActive(): bool
	{
		return $this->isActive;
	}

	public function setIsActive(bool $isActive): self
	{
		$this->isActive = $isActive;
		return $this;
	}

	public function getFullName(): string
	{
		return $this->firstName . " " . $this->lastName;
	}

	public function getAuthObjectId(): string
	{
		return (string) $this->getId();
	}

	public function getAuthToken(): ?string
	{
		return $this->authToken;
	}

	public function setAuthToken(string $token): void
	{
		$this->authToken = $token;
	}

	public function addRole(IAclRole $role): static
	{
		if ($this->roles->contains($role)) {
			return $this;
		}
		$this->roles->add($role);
		return $this;
	}

	/**
	 * @return IAclRole[]
	 */
	public function getRoles(): array
	{
		return $this->roles->toArray();
	}

	public function isAllowed(string $aclResource): bool
	{
		return array_any($this->getRoles(), fn(IAclRole $_role) => $_role->getIsAdmin() || $_role->isAllowed($aclResource));
	}

	public function getGravatar()
	{
		return '//www.gravatar.com/avatar/' . md5($this->getEmail()) . '?s=90&d=mp';
	}

	public function isAdmin(): bool
	{
		return array_any($this->getRoles(), fn(IAclRole $role) => $role->getIsAdmin());
	}

	public function isVisitor(): bool
	{
		return array_any($this->getRoles(), fn(IAclRole $role) => $role->isVisitor());
	}

	public function isCustomer(): bool
	{
		return array_any($this->getRoles(), fn(IAclRole $role) => $role->getIsCustomer());
	}

	public function getAuthMetadata(): array
	{
		return [];
	}

	public function setAuthMetadata(array $metadata): void
	{
	}
}
