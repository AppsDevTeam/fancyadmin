<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclResourceNameEnum: string implements Resource
{
	case BACKOFFICE_ACCOUNTS = 'portal.backoffice.accounts';
	case BACKOFFICE_IDENTITIES = 'portal.backoffice.identities';

	case BACKOFFICE_ROLES_AND_PERMISSIONS = 'portal.backoffice.roles_and_permissions';

	public function getResourceId(): string
	{
		return $this->value;
	}
}
