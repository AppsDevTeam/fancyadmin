<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\User;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait CreatedByNullable
{
	#[ManyToOne(targetEntity: User::class)]
	#[JoinColumn(nullable: true)]
	protected ?User $createdBy = null;

	public function setCreatedBy(?User $createdBy): static
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getCreatedBy(): User
	{
		return $this->createdBy;
	}
}
