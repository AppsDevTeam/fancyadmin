<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclRoleAndPermissionsEnum: string implements Resource
{
	case BACKOFFICE_ROLES_AND_PERMISSIONS = 'backoffice.roles_and_permissions';


	public function getResourceId(): string
	{
		return self::BACKOFFICE_ROLES_AND_PERMISSIONS->value;
	}
}
