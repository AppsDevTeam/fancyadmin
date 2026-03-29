<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use Doctrine\ORM\Mapping as ORM;

trait IsActive
{
	#[ORM\Column(nullable: false, options: ["default" => 1])]
	#[LoggableProperty]
	protected bool $isActive = true;

	public function getIsActive(): bool
	{
		return $this->isActive;
	}

	public function setIsActive(bool $isActive): static
	{
		$this->isActive = $isActive;
		return $this;
	}
}