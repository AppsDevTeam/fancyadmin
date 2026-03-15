<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedBy;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use Doctrine\ORM\Mapping as ORM;

trait AclTrait
{
	use CreatedAt;
	use CreatedBy;
	use IsActive;
	use UpdatedAt;
	use UpdatedBy;

	#[ORM\ManyToOne(targetEntity: 'AclRole')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	protected AclRole $role;

	#[ORM\ManyToOne(targetEntity: 'AclResource')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	protected AclResource $resource;

	public function getRole(): AclRole
	{
		return $this->role;
	}

	public function setRole(AclRole $role): static
	{
		$this->role = $role;
		return $this;
	}

	public function getResource(): AclResource
	{
		return $this->resource;
	}

	public function setResource(AclResource $resource): static
	{
		$this->resource = $resource;
		return $this;
	}
}
