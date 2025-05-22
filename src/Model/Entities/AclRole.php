<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;

trait AclRole
{
	use Identifier;

	#[ORM\Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\ManyToMany(targetEntity: 'AclResource', inversedBy: 'roles', cascade: ["persist"])]
	#[JoinColumn(onDelete: "RESTRICT")]
	#[InverseJoinColumn(onDelete: "RESTRICT")]
	protected Collection $resources;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isAdmin = false;

	public function __construct()
	{
		$this->resources = new ArrayCollection();
	}

	public function getRoleId(): string
	{
		return $this->name;
	}

	public function addResource(AclResourceInterface $resource): static
	{
		if ($this->resources->contains($resource)) {
			return $this;
		}
		$this->resources->add($resource);
		$resource->addRole($this);
		return $this;
	}

	public function removeResource(AclResourceInterface $resource): static
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
	 * @return AclResourceInterface[]
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

	public function isAllowed(string $aclResource): bool
	{
		return array_any($this->getResources(), fn(IAclResource $_resource) => $_resource->getName() === $aclResource);
	}
}
