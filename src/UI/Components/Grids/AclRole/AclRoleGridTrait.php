<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\AclRole;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use App\Model\Entities\AclRole;
use App\Model\Queries\Factories\AclRoleQueryFactory;
use Contributte\Datagrid\Exception\DatagridException;

trait AclRoleGridTrait
{
	use Editable;

	protected AclResourceNameEnum $aclResource = AclResourceNameEnum::BACKOFFICE_ROLES_AND_PERMISSIONS;

	/**
	 * @throws DatagridException
	 */
	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('name', 'fcadmin.grids.aclRole.name');

		if ($this->allowEdit()) {
			$grid->getAction('edit')->setRenderCondition(fn(AclRole $aclRole) => !$aclRole->getIsAdmin());
		}
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AclRoleQueryFactory::class;
	}
}
