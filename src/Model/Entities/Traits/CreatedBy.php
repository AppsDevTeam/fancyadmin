<?php

namespace ADT\FancyAdmin\Model\Entities\Traits;

use App\Model\Entities\Identity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait CreatedBy
{
	#[ManyToOne(targetEntity: Identity::class)]
	#[JoinColumn(nullable: false)]
	protected Identity $createdBy;

	public function setCreatedBy(Identity $createdBy): static
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getCreatedBy(): Identity
	{
		return $this->createdBy;
	}
}
