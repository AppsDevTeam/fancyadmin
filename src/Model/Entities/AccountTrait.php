<?php

namespace ADT\FancyAdmin\Model\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping as ORM;

trait AccountTrait
{
	#[Column(unique: true, nullable: false)]
	protected string $name;

	#[ORM\OneToMany(targetEntity: 'Account', mappedBy: 'parent')]
	protected Collection $accounts;

	#[ORM\ManyToOne(targetEntity: 'Account')]
	protected ?Account $parent = null;
	
	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getParent(): ?Account
	{
		return $this->parent;
	}

	public function setParent(?Account $parent): static
	{
		$this->parent = $parent;
		return $this;
	}
}