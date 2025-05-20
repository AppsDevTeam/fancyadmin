<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IUser;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait UpdatedBy
{
	#[ManyToOne(targetEntity: 'User', cascade: ["persist"])]
	#[JoinColumn(nullable: true)]
	protected ?IUser $updatedBy = null;

	public function setUpdatedBy(?IUser $updatedBy): static
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}

	public function getUpdatedBy(): ?IUser
	{
		return $this->updatedBy;
	}
}
