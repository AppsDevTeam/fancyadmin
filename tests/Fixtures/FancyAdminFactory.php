<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Tests\Fixtures;

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\FancyAdmin;

/**
 * FancyAdmin ma hodne konstruktorovych parametru - tovarna drzi rozumne vychozi hodnoty,
 * aby test menil jen to, na cem mu zalezi.
 */
final class FancyAdminFactory
{
	public static function create(array $overrides = []): FancyAdmin
	{
		$args = [
			'project' => 'testproject',
			'projectName' => 'Test Project',
			'adminHostPath' => 'admin.example.com',
			'lostPasswordEnabled' => true,
			'logoPublicPath' => '/img/logo.svg',
			'logoMenuPath' => '/img/logo-menu.svg',
			'emailBackgroundColor' => '#ffffff',
			'faviconFileNamePng' => null,
			'faviconFileNameSvg' => null,
			'loginPageLogoPath' => '/img/login.svg',
			'logoBitmapPublicPath' => '/img/logo.png',
			'defaultCustomerRoute' => ':PortalCustomer:Home:',
			'defaultBackofficeRoute' => ':PortalBackoffice:Home:',
			'hmr' => false,
			'customerAclResource' => AclResourceNameEnum::CUSTOMER_DASHBOARD,
			'backofficeAclResource' => AclResourceNameEnum::BACKOFFICE_DASHBOARD,
			'fullDataAclResource' => AclResourceNameEnum::FULL_DATA,
			'personalDataAclResource' => AclResourceNameEnum::PROFILE_PERSONAL_DATA,
			'context' => 'admin',
			'jsComponentsConfig' => [],
			'colors' => [],
			'keycloakEnabled' => false,
			'passkeyEnabled' => false,
			'passkeyRpId' => null,
			'passkeyRpName' => null,
			'ssoAllowedHosts' => [],
		];

		return new FancyAdmin(...array_merge($args, $overrides));
	}
}
