<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\Identity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait UpdatedBy
{
	#[ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: true)]
	protected ?Identity $updatedBy = null;

	public function setUpdatedBy(?Identity $updatedBy): static
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}

	public function getUpdatedBy(): ?Identity
	{
		return $this->updatedBy;
	}
}
