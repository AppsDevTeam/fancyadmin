<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Identity;

use ADT\FancyAdmin\Model\Entities\Enums\IdentityScopeEnum;

interface IdentityGrid
{
	/** null = seznam se nedeli a zobrazuje vsechny uzivatele. */
	public ?IdentityScopeEnum $scope { get; set; }
}
