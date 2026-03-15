<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Security\Resource;

class StringResource implements Resource
{
	public function __construct(
		private readonly string $resourceId,
	) {
	}

	public function getResourceId(): string
	{
		return $this->resourceId;
	}
}
