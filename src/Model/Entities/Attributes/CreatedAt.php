<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait CreatedAt
{
	#[Gedmo\Timestampable(on: 'create')]
	#[ORM\Column]
	protected \DateTimeImmutable $createdAt;

	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(\DateTimeImmutable $createdAt): self
	{
		$this->createdAt = $createdAt;
		return $this;
	}
}
