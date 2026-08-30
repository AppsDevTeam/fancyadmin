<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait UpdatedAt
{
	#[Gedmo\Timestampable(on: 'update')]
	#[ORM\Column]
	final protected DateTimeImmutable $updatedAt;

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTimeImmutable $createdAt): static
	{
		$this->updatedAt = $createdAt;
		return $this;
	}
}
