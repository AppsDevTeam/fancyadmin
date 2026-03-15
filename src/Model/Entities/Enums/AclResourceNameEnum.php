<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclResourceNameEnum: string implements Resource
{
	case BACKOFFICE_IDENTITIES_ANONYMIZE = 'portalBackoffice.identities.anonymize';
	case BACKOFFICE_IDENTITIES_SIGNAS = 'portalBackoffice.identities.signAs';

	public function getResourceId(): string
	{
		return $this->value;
	}
}
