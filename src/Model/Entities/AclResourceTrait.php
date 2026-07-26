<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use Doctrine\ORM\Mapping as ORM;

trait AclResourceTrait
{
	#[ORM\Column(unique: true, nullable: false)]
	#[LoggableProperty]
	protected string $name;

	#[ORM\Column]
	#[LoggableProperty]
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
