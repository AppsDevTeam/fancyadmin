<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Security\Resource;

trait AclRoleTrait
{
	use CreatedAt;
	use CreatedByNullable;
	use UpdatedAt;
	use UpdatedBy;

	#[ORM\Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\OneToMany(targetEntity: 'Acl', mappedBy: 'role')]
	protected Collection $acls;

	#[ORM\Column(nullable: true)]
	protected ?string $context = null;

	#[ORM\Column(nullable: false)]
	protected AclRoleTypeEnum $type;

	#[ORM\Column(nullable: false, options: ["default" => 0])]
	protected bool $isAdmin = false;

	public function __construct()
	{
		$this->acls = new ArrayCollection();
	}

	public function getRoleId(): string
	{
		return (string) $this->getName();
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
		return array_map(
			fn(Acl $acl) => $acl->getResource(),
			array_filter($this->acls->toArray(), fn(Acl $acl) => $acl->getIsActive()),
		);
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

	public function isAllowed(Resource $resource): bool
	{
		if ($this->isAdmin) {
			return true;
		}

		return array_any($this->getResources(), fn(AclResource $_resource) => $_resource->getName() === $resource->getResourceId());
	}

	public function getContext(): ?string
	{
		return $this->context;
	}

	public function setContext(?string $context): static
	{
		$this->context = $context;
		return $this;
	}

	public function getType(): AclRoleTypeEnum
	{
		return $this->type;
	}

	public function setType(AclRoleTypeEnum $type): static
	{
		$this->type = $type;
		return $this;
	}
}
