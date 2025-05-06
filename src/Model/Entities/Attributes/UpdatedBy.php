<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\User;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait UpdatedBy
{
	#[ManyToOne(targetEntity: User::class, cascade: ["persist"])]
	#[JoinColumn(nullable: true)]
	protected ?User $updatedBy = null;

	public function setUpdatedBy(?User $updatedBy): static
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}

	public function getUpdatedBy(): ?User
	{
		return $this->updatedBy;
	}
}
