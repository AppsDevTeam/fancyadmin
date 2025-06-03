<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

trait GridFilterQueryTrait
{
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
