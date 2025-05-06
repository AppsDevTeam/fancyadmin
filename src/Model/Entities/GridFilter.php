<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Attributes\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Attributes\CreatedBy;
use ADT\FancyAdmin\Model\Entities\Attributes\CreatedByInterface;
use ADT\FancyAdmin\Model\Entities\Attributes\Identifier;
use ADT\DoctrineForms\Entity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[ORM\Entity]
#[UniqueConstraint(fields: ["grid", "name"])]
class GridFilter extends Entity implements CreatedByInterface
{
	use Identifier;
	use CreatedBy;
	use CreatedAt;

	#[ORM\Column(nullable: false)]
	protected string $grid;

	#[ORM\Column(nullable: false)]
	protected string $name;

	#[Column(type: "json", nullable: false)]
	protected array $value = [];

	public function getGrid(): string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): self
	{
		$this->grid = $grid;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getValue(): array
	{
		return $this->value;
	}

	public function setValue(array $value): self
	{
		$this->value = $value;
		return $this;
	}
}
