<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use ADT\FancyAdmin\Model\Entities\IdentityInterface;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait CreatedByNullable
{
	#[ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: true)]
	protected ?IdentityInterface $createdBy = null;

	public function setCreatedBy(?IdentityInterface $createdBy): static
	{
		$this->createdBy = $createdBy;
		return $this;
	}

	public function getCreatedBy(): IdentityInterface
	{
		return $this->createdBy;
	}
}
