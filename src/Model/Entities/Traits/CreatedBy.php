<?php

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait CreatedBy
{
	// Cil je zamerne kratky nazev, ne trida: Doctrine ho dohleda v namespace entity, ktera
	// trait pouziva, tedy u projektove Identity. Stejne to delaji UpdatedBy a CreatedByNullable.
	#[ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: false)]
	final protected Identity $createdBy;

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
