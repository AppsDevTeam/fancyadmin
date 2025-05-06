<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Attributes;

use Doctrine\ORM\Mapping as ORM;

trait Identifier
{
	#[ORM\Id]
	#[ORM\Column(nullable: false)]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function __clone()
	{
		$this->id = null;
	}
}
