<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Traits;

interface TreeInterface
{
	public function getParent(): ?static;

	/**
	 * @return static[]
	 */
	public function getChildren(): array;
}
