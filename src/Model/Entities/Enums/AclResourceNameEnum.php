<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclResourceNameEnum: string implements Resource
{
	case BACKOFFICE_PERMISSIONS = 'backoffice.permissions';


	function getResourceId(): string
	{
		return self::BACKOFFICE_PERMISSIONS->value;
	}
}
