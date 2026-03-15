<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Profile;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Queries\Factories\ProfileQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Deletable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\IdentityData;
use ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword\ResetPassword;

trait ProfileGridTrait
{
	use Editable;
	use Deletable;
	use IdentityData;
	use ResetPassword;

	public function initGrid(DataGrid $grid): void
	{
		$this->addIdentityData($grid, 'identity.');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ProfileQueryFactory::class;
	}
}
