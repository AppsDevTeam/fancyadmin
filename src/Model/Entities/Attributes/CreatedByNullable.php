<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IUser;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait CreatedByNullable
{
	#[ManyToOne(targetEntity: 'User')]
	#[JoinColumn(nullable: true)]
	protected ?IUser $createdBy = null;

	public function setCreatedBy(?IUser $createdBy): static
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getCreatedBy(): IUser
	{
		return $this->createdBy;
	}
}
