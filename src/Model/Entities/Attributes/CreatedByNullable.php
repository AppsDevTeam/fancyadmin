<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\Identity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait CreatedByNullable
{
	#[ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: true)]
	protected ?Identity $createdBy = null;

	public function setCreatedBy(?Identity $createdBy): static
	{
		$this->createdBy = $createdBy;
		return $this;
	}

	public function getCreatedBy(): Identity
	{
		return $this->createdBy;
	}
}
