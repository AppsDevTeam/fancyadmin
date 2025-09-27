<?php

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\ORM\Mapping\Column;

trait AccountTrait
{
	#[Column(unique: true, nullable: false)]
	protected string $name;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}
}