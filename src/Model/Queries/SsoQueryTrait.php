<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

trait SsoQueryTrait
{
	public function byName(string $name): static
	{
		return $this->by('name', $name);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}
}
