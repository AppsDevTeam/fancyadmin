<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclResourceNameEnum: string implements Resource
{
	case BACKOFFICE_ACCOUNTS = 'portalBackoffice.accounts';
	case BACKOFFICE_IDENTITIES = 'portalBackoffice.identities';

	case BACKOFFICE_CONFIGURATIONS = 'portalBackoffice.configurations';

	case BACKOFFICE_ROLES_AND_PERMISSIONS = 'portalBackoffice.roles_and_permissions';

	public function getResourceId(): string
	{
		return $this->value;
	}
}
