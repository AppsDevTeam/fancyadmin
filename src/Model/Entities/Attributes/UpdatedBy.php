<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IdentityInterface;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait UpdatedBy
{
	#[ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: true)]
	protected ?IdentityInterface $updatedBy = null;

	public function setUpdatedBy(?IdentityInterface $updatedBy): static
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}

	public function getUpdatedBy(): ?IdentityInterface
	{
		return $this->updatedBy;
	}
}
