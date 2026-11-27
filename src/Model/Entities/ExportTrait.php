<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\ORM\Mapping as ORM;

trait ExportTrait
{
	use \ADT\Exporter\Model\Entities\ExportTrait;

	// Krátký název cíle Doctrine dohledá v namespace entity, která trait používá, tedy u projektové Identity
	#[ORM\ManyToOne(targetEntity: 'Identity')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?Identity $createdBy = null;

	public function getCreatedBy(): ?Identity
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?Identity $createdBy): static
	{
		$this->createdBy = $createdBy;

		return $this;
	}
}
