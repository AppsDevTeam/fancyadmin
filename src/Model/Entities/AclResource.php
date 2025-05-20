<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** @mixin  IAclResource */
#[ORM\Entity]
trait AclResource
{
	use Identifier;

	#[ORM\Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\Column(nullable: true)]
	protected ?string $title = null;

	#[ORM\ManyToMany(targetEntity: 'AclRole', mappedBy: 'resources', cascade: ["persist"])]
	protected Collection $roles;

	public function __construct()
	{
		$this->roles = new ArrayCollection();
	}

	public function addRole(IAclRole $role): static
	{
		if ($this->roles->contains($role)) {
			return $this;
		}
		$this->roles->add($role);
		$role->addResource($this);
		return $this;
	}

	public function removeRole(IAclRole $role): static
	{
		if (!$this->roles->contains($role)) {
			return $this;
		}
		$this->roles->removeElement($role);
		$role->removeResource($this);
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setTitle(?string $title): static
	{
		$this->title = $title;
		return $this;
	}
}
