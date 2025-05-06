<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

use ADT\FancyAdmin\Model\Entities\GridFilter;
use ADT\FancyAdmin\Model\Queries\Base\BaseQuery;

/**
 * @extends BaseQuery<GridFilter>
 */
class GridFilterQuery extends BaseQuery
{
	public function applySecurityFilter(): void
	{
		// TODO
	}

	public function byGrid(string $grid): self
	{
		return $this->by('grid', $grid);
	}

	public function byName(string $name): self
	{
		return $this->by('name', $name);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}
}
