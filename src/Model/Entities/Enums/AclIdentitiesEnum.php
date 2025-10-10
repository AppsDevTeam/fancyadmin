<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclIdentitiesEnum: string implements Resource
{
	case BACKOFFICE_IDENTITIES = 'backoffice.identities';


	public function getResourceId(): string
	{
		return self::BACKOFFICE_IDENTITIES->value;
	}
}
