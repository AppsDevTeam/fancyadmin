<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use Attribute;
use Nette\Security\Resource;

#[Attribute]
final readonly class SecurityCheckAttribute
{
	public function __construct(private Resource $resourceName)
	{
	}

	public function getResourceName(): Resource
	{
		return $this->resourceName;
	}
}
