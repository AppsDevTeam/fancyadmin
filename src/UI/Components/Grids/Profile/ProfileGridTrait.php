<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Profile;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use ADT\FancyAdmin\Model\Queries\Factories\ProfileQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;

trait ProfileGridTrait
{
	use Editable;

	protected AclResourceNameEnum $aclResource = AclResourceNameEnum::BACKOFFICE_ACCOUNTS;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addFilterText('search', '', ['name']);
		$grid->addColumnText('fullName', 'fcadmin.grids.profile.fullName', 'identity.fullName');
		$grid->addColumnText('email', 'fcadmin.grids.profile.email', 'identity.email');
		$grid->addColumnText('phoneNumber', 'fcadmin.grids.profile.phoneNumber', 'identity.phoneNumber');
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ProfileQueryFactory::class;
	}
}
