<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Role;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleAndPermissionsEnum;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use App\Model\Entities\AclRole;
use App\Model\Queries\Factories\AclRoleQueryFactory;
use Contributte\Datagrid\Exception\DatagridException;

trait RoleGridTrait
{
	use Editable;

	protected string $aclResource = AclRoleAndPermissionsEnum::BACKOFFICE_ROLES_AND_PERMISSIONS->value;

	/**
	 * @throws DatagridException
	 */
	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('name', 'Name');

		if ($this->allowEdit()) {
			$grid->getAction('edit')->setRenderCondition(fn(AclRole $aclRole) => !$aclRole->getIsAdmin());
		}
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return AclRoleQueryFactory::class;
	}
}
