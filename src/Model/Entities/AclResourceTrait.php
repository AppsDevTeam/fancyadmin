<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\ORM\Mapping as ORM;

trait AclResourceTrait
{
	#[ORM\Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\Column]
	protected string $title;

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function setTitle(string $title): static
	{
		$this->title = $title;
		return $this;
	}
}
