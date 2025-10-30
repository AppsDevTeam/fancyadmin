<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Queries;

trait ConfigurationQueryTrait
{
	public function byKey(string|array $key): static
	{
		return $this->by('key', $key);
	}
}
