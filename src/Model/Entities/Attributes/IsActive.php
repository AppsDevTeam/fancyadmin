<?php

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use Doctrine\ORM\Mapping as ORM;

trait IsActive
{
	#[ORM\Column(nullable: false, options: ["default" => 1])]
	protected bool $isActive = true;

	public function getIsActive(): bool
	{
		return $this->isActive;
	}

	public function setIsActive(bool $isActive): self
	{
		$this->isActive = $isActive;
		return $this;
	}
}