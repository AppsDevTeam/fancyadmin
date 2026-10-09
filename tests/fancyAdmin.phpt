<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Security\SsoHostAllowlist;
use ADT\FancyAdmin\Tests\Fixtures\FancyAdminFactory;
use Tester\Assert;

/**
 * FancyAdmin - konfigurace administrace prevedena z neonu do objektu.
 */

require __DIR__ . '/bootstrap.php';


test('zakladni udaje projektu', function () {
	$fancyAdmin = FancyAdminFactory::create();

	Assert::same('testproject', $fancyAdmin->getProject());
	Assert::same('Test Project', $fancyAdmin->getProjectName());
	Assert::same('admin.example.com', $fancyAdmin->getAdminHostPath());
	Assert::same('admin', $fancyAdmin->getContext());
	Assert::true($fancyAdmin->isLostPasswordEnabled());
	Assert::false($fancyAdmin->getHmr());
});


test('cesty k logum a favicone', function () {
	$fancyAdmin = FancyAdminFactory::create();

	Assert::same('/img/logo.svg', $fancyAdmin->getLogoPublicPath());
	Assert::same('/img/logo-menu.svg', $fancyAdmin->getLogoMenuPath());
	Assert::same('/img/login.svg', $fancyAdmin->getLoginPageLogoPath());
	Assert::same('/img/logo.png', $fancyAdmin->getLogoBitmapPublicPath());
	Assert::null($fancyAdmin->getFaviconFileNamePng());
	Assert::null($fancyAdmin->getFaviconFileNameSvg());

	$fancyAdmin->setFaviconFileNamePng('favicon.png')->setFaviconFileNameSvg('favicon.svg');
	Assert::same('favicon.png', $fancyAdmin->getFaviconFileNamePng());
	Assert::same('favicon.svg', $fancyAdmin->getFaviconFileNameSvg());
});


test('vychozi routy', function () {
	$fancyAdmin = FancyAdminFactory::create();

	Assert::same(':PortalCustomer:Home:', $fancyAdmin->getDefaultCustomerRoute());
	Assert::same(':PortalBackoffice:Home:', $fancyAdmin->getDefaultBackofficeRoute());
});


test('ACL zdroje pro jednotlive casti administrace', function () {
	$fancyAdmin = FancyAdminFactory::create();

	Assert::same(AclResourceNameEnum::CUSTOMER_DASHBOARD, $fancyAdmin->getCustomerAclResource());
	Assert::same(AclResourceNameEnum::BACKOFFICE_DASHBOARD, $fancyAdmin->getBackofficeAclResource());
	Assert::same(AclResourceNameEnum::FULL_DATA, $fancyAdmin->getFullDataAclResource());
});


test('barvy a konfigurace JS komponent', function () {
	$fancyAdmin = FancyAdminFactory::create(['colors' => ['primaryColor' => '#3366cc']]);

	Assert::same(['primaryColor' => '#3366cc'], $fancyAdmin->getColors());
	Assert::same([], $fancyAdmin->getJsComponentsConfig());

	Assert::same($fancyAdmin, $fancyAdmin->setJsComponentsConfig(['a' => 1]));
	Assert::same(['a' => 1], $fancyAdmin->getJsComponentsConfig());

	Assert::same('#ffffff', $fancyAdmin->getEmailBackgroundColor());
	Assert::same('#000000', $fancyAdmin->setEmailBackgroundColor('#000000')->getEmailBackgroundColor());
});


test('Keycloak je ve vychozim stavu vypnuty a manager chybi', function () {
	$fancyAdmin = FancyAdminFactory::create();

	Assert::false($fancyAdmin->isKeycloakEnabled());
	Assert::null($fancyAdmin->getKeycloakManager());
});


test('allowlist hostu SSO se sklada z konfigurace', function () {
	$fancyAdmin = FancyAdminFactory::create(['ssoAllowedHosts' => ['AUTH.example.com']]);

	$allowlist = $fancyAdmin->getSsoHostAllowlist();

	Assert::type(SsoHostAllowlist::class, $allowlist);
	Assert::same(['auth.example.com'], $allowlist->getHosts());
	Assert::true($allowlist->allows('https://auth.example.com/realms/test'));
});


test('prazdna konfigurace hostu nepousti nic', function () {
	// Fail-closed: projekt se SSO si hosty vypsat musi.
	Assert::same([], FancyAdminFactory::create()->getSsoHostAllowlist()->getHosts());
	Assert::false(FancyAdminFactory::create()->getSsoHostAllowlist()->allows('https://auth.example.com'));
});


test('allowlist se sklada pokazde znovu', function () {
	// Neni cachovany - kdyby se konfigurace za behu zmenila, projevi se to.
	$fancyAdmin = FancyAdminFactory::create(['ssoAllowedHosts' => ['auth.example.com']]);

	Assert::notSame($fancyAdmin->getSsoHostAllowlist(), $fancyAdmin->getSsoHostAllowlist());
	Assert::equal($fancyAdmin->getSsoHostAllowlist(), $fancyAdmin->getSsoHostAllowlist());
});


test('passkeys jsou ve vychozim stavu vypnute', function () {
	$fancyAdmin = FancyAdminFactory::create();

	Assert::false($fancyAdmin->isPasskeyEnabled());
	Assert::null($fancyAdmin->getPasskeyRpId());
});


test('nazev Relying Party se bere z nazvu projektu', function () {
	Assert::same('Test Project', FancyAdminFactory::create()->getPasskeyRpName());
	Assert::same('Muj admin', FancyAdminFactory::create(['passkeyRpName' => 'Muj admin'])->getPasskeyRpName());
});
