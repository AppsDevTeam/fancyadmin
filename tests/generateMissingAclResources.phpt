<?php

declare(strict_types=1);

use ADT\FancyAdmin\Console\Command;
use ADT\FancyAdmin\Console\CreateIdentityCommand;
use ADT\FancyAdmin\Console\GenerateMissingAclResourcesCommand;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Tester\Assert;

/**
 * Prikaz, ktery dogeneruje chybejici ACL zdroje.
 *
 * Nazev zdroje se odvozuje z namespace presenteru - to je ta cast, ktera se muze rozejit
 * s tim, co ocekava menu (NavbarMenu::resolveAclResources) nebo presenter.
 */

require __DIR__ . '/bootstrap.php';


function resolveResourceName(string $class): ?string
{
	$command = new ReflectionClass(GenerateMissingAclResourcesCommand::class)->newInstanceWithoutConstructor();

	return new ReflectionMethod($command, 'resolveResourceName')->invoke($command, $class);
}

function findEnumResources(array $classes): array
{
	$command = new ReflectionClass(GenerateMissingAclResourcesCommand::class)->newInstanceWithoutConstructor();

	return new ReflectionMethod($command, 'findEnumResources')->invoke($command, $classes);
}


test('nazev zdroje se sklada z modulu a presenteru', function () {
	Assert::same(
		'portalBackoffice.accounts',
		resolveResourceName('App\UI\Portal\Backoffice\Presenters\Accounts\AccountsPresenter'),
	);
	Assert::same(
		'portalCustomer.orders',
		resolveResourceName('App\UI\Portal\Customer\Presenters\Orders\OrdersPresenter'),
	);
});


test('modul muze byt jednoslovny i vicedilny', function () {
	Assert::same('portal.home', resolveResourceName('App\UI\Portal\Presenters\Home\HomePresenter'));
	Assert::same(
		'portalBackofficeSprava.devices',
		resolveResourceName('App\UI\Portal\Backoffice\Sprava\Presenters\Devices\DevicesPresenter'),
	);
});


test('presenter mimo podslozku se preskoci', function () {
	// Typicky BasePresenter - vlastni obrazovku nema, takze ani zdroj nepotrebuje.
	Assert::null(resolveResourceName('App\UI\Portal\Backoffice\Presenters\BasePresenter'));
});


test('trida bez segmentu Presenters se preskoci', function () {
	Assert::null(resolveResourceName('App\Model\Entities\Identity'));
	Assert::null(resolveResourceName('Identity'));
});


test('POZOR: odvozeni pocita s prefixem App\\UI\\', function () {
	// Modul se bere jako vse mezi druhym segmentem a "Presenters". U presenteru mimo
	// App\UI\ proto vznikne nesmyslny nazev misto null - odpovida to dokumentaci metody,
	// ale projekt s jinou strukturou to nepozna.
	Assert::same('home.home', resolveResourceName('App\Presenters\Home\HomePresenter'));

	// Uplne kratky namespace uz na modul nezbyde.
	Assert::null(resolveResourceName('App\UI\Presenters\Home\HomePresenter'));
});


test('bere se posledni segment Presenters', function () {
	// Kdyby se nekdo jmenoval Presenters i v modulu, rozhoduje ten blize k presenteru.
	Assert::same(
		'portalPresentersBackoffice.accounts',
		resolveResourceName('App\UI\Portal\Presenters\Backoffice\Presenters\Accounts\AccountsPresenter'),
	);
});


test('zdroje se sbiraji z retezcovych enumu implementujicich Resource', function () {
	$resources = findEnumResources([AclResourceNameEnum::class]);

	Assert::same(array_map(fn($case) => $case->value, AclResourceNameEnum::cases()), $resources);
});


test('jine tridy a enumy se preskoci', function () {
	Assert::same([], findEnumResources([
		ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum::class,
		ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum::class,
		ADT\FancyAdmin\Model\FancyAdmin::class,
		'NeexistujiciTrida',
	]));
});


test('prikazy maji nazev a popis pro konzoli', function () {
	$attribute = new ReflectionClass(GenerateMissingAclResourcesCommand::class)->getAttributes(AsCommand::class)[0]->newInstance();
	Assert::same('fancyadmin:generate-missing-acl-resources', $attribute->name);

	$attribute = new ReflectionClass(CreateIdentityCommand::class)->getAttributes(AsCommand::class)[0]->newInstance();
	Assert::same('fancyadmin:create-identity', $attribute->name);
});


test('oba prikazy bezi pod zamkem', function () {
	// Spolecny predek nastavi uloziste zamku a prikaz obali lock()/unlock().
	Assert::type(Command::class, new ReflectionClass(GenerateMissingAclResourcesCommand::class)->newInstanceWithoutConstructor());
	Assert::type(Command::class, new ReflectionClass(CreateIdentityCommand::class)->newInstanceWithoutConstructor());
	Assert::true(method_exists(Command::class, 'setLocksDir'));
});
