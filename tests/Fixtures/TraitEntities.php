<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullable;
use ADT\FancyAdmin\Model\Entities\Traits\CreatedByNullableInterface;
use ADT\FancyAdmin\Model\Entities\Traits\IsActive;
use ADT\FancyAdmin\Model\Entities\Traits\IsActiveInterface;
use ADT\FancyAdmin\Model\Entities\Traits\SoftDeleteable;
use ADT\FancyAdmin\Model\Entities\Traits\SoftDeleteableInterface;
use ADT\FancyAdmin\Model\Entities\Traits\Tree;
use ADT\FancyAdmin\Model\Entities\Traits\TreeInterface;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedAt;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedBy;
use ADT\FancyAdmin\Model\Entities\Traits\UpdatedByInterface;
use Doctrine\Common\Collections\ArrayCollection;

/** Nositel sdilenych entitnich trait - tady se testuji samy o sobe, bez konkretni entity. */
final class TestTimestampedEntity implements IsActiveInterface, CreatedByNullableInterface, UpdatedByInterface
{
	use Identifier;
	use CreatedAt;
	use UpdatedAt;
	use CreatedByNullable;
	use UpdatedBy;
	use IsActive;
}

final class TestSoftDeleteableEntity implements SoftDeleteableInterface
{
	use Identifier;
	use SoftDeleteable;

	public function __construct(?int $id = null)
	{
		$this->id = $id;
	}
}

final class TestTreeNode implements TreeInterface
{
	use Identifier;
	use Tree;

	public function __construct(private readonly string $label = '')
	{
		$this->initTree();
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function addChild(self $child): static
	{
		$this->children->add($child);
		$child->setParent($this);
		return $this;
	}
}
