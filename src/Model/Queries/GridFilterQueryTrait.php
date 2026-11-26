<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\Enums\GridFilterType;
use Doctrine\ORM\QueryBuilder;

/** Bez byType() vrací jen pokročilé filtry - datagrid-components o sestavách neví a vypsal by je mezi ně. */
trait GridFilterQueryTrait
{
	public function init(): void
	{
		parent::init();

		$this->byType(GridFilterType::Advanced);
	}

	public function byGrid(string $grid): static
	{
		return $this->by('grid', $grid);
	}

	public function byName(string $name): static
	{
		return $this->by('name', $name);
	}

	public function byType(GridFilterType $type): static
	{
		$this->filter['type'] = function (QueryBuilder $qb) use ($type): void {
			$qb->andWhere($this->entityAlias . '.type = :gridFilterType')
				->setParameter('gridFilterType', $type->value);
		};

		return $this;
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}
}
