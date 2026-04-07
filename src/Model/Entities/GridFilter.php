<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities;

use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[UniqueConstraint(fields: ["grid", "name"])]
trait GridFilter
{
	use CreatedByNullable;
	use CreatedAt;

	#[ORM\Column(nullable: false)]
	protected string $grid;

	#[ORM\Column(nullable: false)]
	protected string $name;

	#[Column(type: "json", nullable: false)]
	protected array $value = [];

	#[ORM\ManyToOne(targetEntity: 'Account')]
	#[JoinColumn(nullable: true)]
	protected ?Account $account = null;

	public function getGrid(): string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): static
	{
		$this->grid = $grid;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getValue(): array
	{
		return $this->value;
	}

	public function setValue(array $value): static
	{
		$this->value = $value;
		return $this;
	}

	public function getAccount(): ?Account
	{
		return $this->account;
	}

	public function setAccount(?Account $account): self
	{
		$this->account = $account;
		return $this;
	}
}
