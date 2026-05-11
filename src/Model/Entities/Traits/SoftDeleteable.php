<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\FancyAdmin\Model\Entities\Identity;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

trait SoftDeleteable
{
	abstract public function getId();

	#[Column(nullable: false, options: ["default" => 0])]
	protected int $isDeleted = 0;

	#[Column(type: Types::DATETIME_MUTABLE, nullable: true)]
	protected ?DateTime $deletedAt = null;

	#[ManyToOne(targetEntity: 'Identity')]
	#[JoinColumn(nullable: true)]
	protected ?Identity $deletedBy = null;

	public function setIsDeleted(): static
	{
		$this->isDeleted = $this->getId();
		return $this;
	}

	public function getIsDeleted(): int
	{
		return $this->isDeleted;
	}

	public function setDeletedAt(?DateTime $deletedAt = null): static
	{
		$this->deletedAt = $deletedAt;
		return $this;
	}

	public function getDeletedAt(): ?DateTime
	{
		return $this->deletedAt;
	}

	public function isDeleted(): bool
	{
		return null !== $this->deletedAt;
	}

	public function setDeletedBy(?Identity $deletedBy): static
	{
		$this->deletedBy = $deletedBy;
		return $this;
	}

	public function getDeletedBy(): ?Identity
	{
		return $this->deletedBy;
	}
}
