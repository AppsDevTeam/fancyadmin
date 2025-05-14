<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use Attribute;

#[Attribute]
final readonly class SecurityCheckAttribute
{
	public function __construct(private string $resourceName)
	{
	}

	public function getResourceName(): string
	{
		return $this->resourceName;
	}
}
