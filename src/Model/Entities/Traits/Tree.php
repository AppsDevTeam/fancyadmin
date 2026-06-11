<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait Tree
{
	#[Gedmo\SortableGroup]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	protected ?self $parent = null;

	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	protected Collection $children;

	protected function initTree(): void
	{
		$this->children = new ArrayCollection();
	}

	public function getParent(): ?static
	{
		return $this->parent;
	}

	public function setParent(?self $parent): static
	{
		if ($parent === $this) {
			$parent = null;
		}
		$this->parent = $parent;
		return $this;
	}

	/**
	 * @return static[]
	 */
	public function getChildren(): array
	{
		return $this->children->toArray();
	}
}
