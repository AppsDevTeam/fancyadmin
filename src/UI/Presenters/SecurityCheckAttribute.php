<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use Attribute;

#[Attribute]
final readonly class SecurityCheckAttribute
{
	public function __construct(private AclResourceNameEnum $resourceName)
	{
	}

	public function getResourceName(): AclResourceNameEnum
	{
		return $this->resourceName;
	}
}
