<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Entities\Enums;

use Nette\Security\Resource;

enum AclResourceNameEnum: string implements Resource
{
	case BACKOFFICE_IDENTITIES_ANONYMIZE = 'portalBackoffice.identities.anonymize';
	case BACKOFFICE_IDENTITIES_SIGNAS = 'portalBackoffice.identities.signAs';
	case CUSTOMER_DASHBOARD = 'customer.dashboard';
	case BACKOFFICE_DASHBOARD = 'portalBackoffice.dashboard';
	case FULL_DATA = 'fullData';

	// AI asistent: čtení konverzací ostatních uživatelů (jen pro čtení, ne psaní do nich)
	case BACKOFFICE_AI_VIEW_OTHERS = 'portalBackoffice.ai.viewOthers';
	// AI asistent: přístup k datům přes MCP server (vlastní tokeny + endpoint)
	case BACKOFFICE_AI_MCP = 'portalBackoffice.ai.mcp';

	// Modulově neutrální - Profile presenter existuje v obou modulech, jde o tutéž akci.
	case PROFILE_PERSONAL_DATA = 'profile.personalData';

	public function getResourceId(): string
	{
		return $this->value;
	}
}
