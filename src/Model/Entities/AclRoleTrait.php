<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Nette\Security\Resource;

trait AclRoleTrait
{
	#[ORM\Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\ManyToMany(targetEntity: 'AclResource', inversedBy: 'roles', cascade: ["persist"])]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $resources;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isAdmin = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isIdentity = false;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isProfile = false;

	public function __construct()
	{
		$this->resources = new ArrayCollection();
	}

	public function getRoleId(): string
	{
		return (string) $this->getId();
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
		return $this->name;
	}

	/**
	 * @return AclResource[]
	 */
	public function getResources(): array
	{
		return $this->resources->toArray();
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

	public function isAllowed(Resource $aclResource): bool
	{
		if ($this->isAdmin) {
			return true;
		}

		return array_any($this->getResources(), fn(Resource $_resource) => $_resource === $aclResource);
	}

	public function isIdentity(): bool
	{
		return $this->isIdentity;
	}

	public function setIsIdentity(bool $isIdentity): static
	{
		$this->isIdentity = $isIdentity;
		return $this;
	}

	public function isProfile(): bool
	{
		return $this->isProfile;
	}

	public function setIsProfile(bool $isProfile): static
	{
		$this->isProfile = $isProfile;
		return $this;
	}
}
