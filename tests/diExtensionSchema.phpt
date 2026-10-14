<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Menu\StringResource;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;
use Tester\Assert;

/**
 * Konfiguracni schema rozsireni.
 *
 * Schema je smlouva mezi balickem a neonem projektu - vychozi hodnoty i povinne polozky
 * se tu uvadeji doslova, protoze jejich zmena je zmena rozhrani.
 */

require __DIR__ . '/bootstrap.php';


const REQUIRED_COLORS = [
	'backgroundColor' => '#ffffff',
	'dashboardAccentColor' => '#3366cc',
	'primaryColor' => '#3366cc',
	'primaryColorDark' => '#2952a3',
	'primaryColorDark20' => '#1f3d7a',
	'secondaryColor' => '#66cc33',
	'secondaryColorDark' => '#52a329',
	'secondaryColorDarker' => '#3d7a1f',
	'ternaryColor' => '#cccccc',
	'ternaryTextColor' => '#333333',
	'loginBackground' => '#000000',
	'loginInputTextColor' => '#ffffff',
	'loginBackgroundInput' => '#222222',
	'loginBackgroundInputFocus' => '#333333',
	'inputBorder' => '#cccccc',
	'inputFocusBorder' => '#3366cc',
	'inputFocusBackground' => '#f5f5f5',
];

function process(array $config = []): object
{
	return new Processor()->process(
		new FancyAdminExtension()->getConfigSchema(),
		$config + ['locksDir' => '/var/lock', 'colors' => REQUIRED_COLORS],
	);
}


test('rozsireni je bezne Nette rozsireni a poskytovatel prekladu', function () {
	$extension = new FancyAdminExtension();

	Assert::type(CompilerExtension::class, $extension);
	Assert::type(TranslationProviderInterface::class, $extension);
});


test('vychozi hodnoty konfigurace', function () {
	$config = process();

	Assert::null($config->project);
	Assert::null($config->projectName);
	Assert::null($config->adminHostPath);
	Assert::same(':PortalCustomer:Home:', $config->defaultCustomerRoute);
	Assert::same(':PortalBackoffice:Home:', $config->defaultBackofficeRoute);
	Assert::true($config->lostPasswordEnabled);
	Assert::false($config->hmr);
	Assert::null($config->context);
	Assert::same([], $config->jsComponentsConfig);
	Assert::null($config->faviconFileNamePng);
	Assert::null($config->faviconFileNameSvg);
});


test('vychozi ACL zdroje', function () {
	$config = process();

	Assert::same(AclResourceNameEnum::CUSTOMER_DASHBOARD, $config->customerAclResource);
	Assert::same(AclResourceNameEnum::BACKOFFICE_DASHBOARD, $config->backofficeAclResource);
	Assert::same(AclResourceNameEnum::FULL_DATA, $config->fullDataAclResource);
});


test('ACL zdroj jde prebit vlastnim objektem', function () {
	$resource = new StringResource('vlastni.dashboard');

	Assert::same($resource, process(['customerAclResource' => $resource])->customerAclResource);
});


test('ACL zdroj musi byt Resource, ne retezec', function () {
	Assert::exception(fn() => process(['customerAclResource' => 'customer.dashboard']), ValidationException::class);
});


test('Keycloak je vypnuty, ale validace certifikatu zapnuta', function () {
	$config = process();

	Assert::false($config->keycloakEnabled);
	// Vypnout se smi jen pro lokalni vyvoj se self-signed certifikatem.
	Assert::true($config->keycloakVerifySsl);
});


test('passkeys jsou vypnute a rpId se odvozuje', function () {
	$config = process();

	Assert::false($config->passkeyEnabled);
	Assert::null($config->passkeyRpId);
	Assert::null($config->passkeyRpName);
});


test('allowlist hostu SSO je ve vychozim stavu prazdny', function () {
	// Fail-closed: projekt se SSO si hosty vypsat musi.
	Assert::same([], process()->ssoAllowedHosts);
	Assert::same(['auth.example.com', 'auth2.example.com'], process(['ssoAllowedHosts' => ['auth.example.com', 'auth2.example.com']])->ssoAllowedHosts);
});


test('allowlist prijme jen seznam retezcu', function () {
	Assert::exception(fn() => process(['ssoAllowedHosts' => 'auth.example.com']), ValidationException::class);
	Assert::exception(fn() => process(['ssoAllowedHosts' => [123]]), ValidationException::class);
	Assert::exception(fn() => process(['ssoAllowedHosts' => ['a' => 'auth.example.com']]), ValidationException::class);
});


test('adresar pro zamky je povinny', function () {
	// Bez nej by konzolove prikazy nemely kam psat zamky a spadly by az za behu.
	Assert::exception(
		fn() => new Processor()->process(new FancyAdminExtension()->getConfigSchema(), ['colors' => REQUIRED_COLORS]),
		ValidationException::class,
		"%A%'locksDir'%A%",
	);
});


test('barvy jsou povinne vsechny', function () {
	foreach (array_keys(REQUIRED_COLORS) as $color) {
		$colors = REQUIRED_COLORS;
		unset($colors[$color]);

		Assert::exception(
			fn() => new Processor()->process(new FancyAdminExtension()->getConfigSchema(), ['locksDir' => '/var/lock', 'colors' => $colors]),
			ValidationException::class,
			'%A%' . $color . '%A%',
			null,
			$color,
		);
	}
});


test('nepovinne barvy zustavaji prazdne', function () {
	$colors = process()->colors;

	Assert::null($colors->sidePanelItemColor);
	Assert::null($colors->textColor);
	Assert::null($colors->loginPageBackground);
	Assert::null($colors->loginPageTextColor);
});


test('nepovinne barvy jdou doplnit', function () {
	$colors = process(['colors' => REQUIRED_COLORS + ['textColor' => '#111111']])->colors;

	Assert::same('#111111', $colors->textColor);
});


test('neznama polozka konfigurace se odmitne', function () {
	// Preklep v neonu se ma poznat pri kompilaci, ne tim, ze nastaveni tise nefunguje.
	Assert::exception(fn() => process(['neexistujiciVolba' => true]), ValidationException::class);
	Assert::exception(
		fn() => process(['colors' => REQUIRED_COLORS + ['neexistujiciBarva' => '#fff']]),
		ValidationException::class,
	);
});


test('logicke volby prijmou jen bool', function () {
	foreach (['lostPasswordEnabled', 'hmr', 'keycloakEnabled', 'keycloakVerifySsl', 'passkeyEnabled'] as $option) {
		Assert::exception(fn() => process([$option => 'ano']), ValidationException::class, null, null, $option);
	}
});
