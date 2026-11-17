<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use Attribute;
use Nette\Security\Resource;

// Opakovatelný: akce smí vyžadovat víc práv, kontrolují se všechna
#[Attribute(Attribute::TARGET_ALL | Attribute::IS_REPEATABLE)]
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
