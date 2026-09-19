<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Enums\AclRoleTypeEnum;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationType;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\Forms\BlockNameEnum;
use ADT\Forms\BlockName;
use Nette\Security\Resource;
use Tester\Assert;

/**
 * Vyctove typy balicku.
 *
 * Hodnoty se ukladaji do databaze a pouzivaji v konfiguraci projektu, takze zmena
 * hodnoty je zmena rozhrani - testy je proto uvadi doslova.
 */

require __DIR__ . '/bootstrap.php';


test('nazvy ACL zdroju', function () {
	Assert::same('portalBackoffice.identities.anonymize', AclResourceNameEnum::BACKOFFICE_IDENTITIES_ANONYMIZE->value);
	Assert::same('portalBackoffice.identities.signAs', AclResourceNameEnum::BACKOFFICE_IDENTITIES_SIGNAS->value);
	Assert::same('customer.dashboard', AclResourceNameEnum::CUSTOMER_DASHBOARD->value);
	Assert::same('portalBackoffice.dashboard', AclResourceNameEnum::BACKOFFICE_DASHBOARD->value);
	Assert::same('fullData', AclResourceNameEnum::FULL_DATA->value);
	Assert::same('profile.personalData', AclResourceNameEnum::PROFILE_PERSONAL_DATA->value);
	Assert::count(6, AclResourceNameEnum::cases());
});


test('ACL zdroj z enumu se da pouzit vsude, kde se ceka Resource', function () {
	foreach (AclResourceNameEnum::cases() as $case) {
		Assert::type(Resource::class, $case);
		Assert::same($case->value, $case->getResourceId());
	}
});


test('typy roli', function () {
	Assert::same('identity', AclRoleTypeEnum::IDENTITY->value);
	Assert::same('profile', AclRoleTypeEnum::PROFILE->value);
	Assert::count(2, AclRoleTypeEnum::cases());
	Assert::same(AclRoleTypeEnum::IDENTITY, AclRoleTypeEnum::from('identity'));
	Assert::null(AclRoleTypeEnum::tryFrom('neexistuje'));
});


test('typy konfiguracnich polozek', function () {
	Assert::same('json', ConfigurationTypeEnum::TYPE_JSON->value);
	Assert::same('plaintext', ConfigurationTypeEnum::TYPE_PLAINTEXT->value);
	Assert::same('select', ConfigurationTypeEnum::TYPE_SELECT->value);
	Assert::same('file', ConfigurationTypeEnum::TYPE_FILE->value);
	Assert::count(4, ConfigurationTypeEnum::cases());
	Assert::type(ConfigurationType::class, ConfigurationTypeEnum::TYPE_JSON);
});


test('seznam typu pro formular ma klic i popisek stejny', function () {
	Assert::same(
		['json' => 'json', 'plaintext' => 'plaintext', 'select' => 'select', 'file' => 'file'],
		ConfigurationTypeEnum::list(),
	);
});


test('velikosti bocniho panelu', function () {
	Assert::same('sm', SidePanelSize::Small->value);
	Assert::same('md', SidePanelSize::Medium->value);
	Assert::same('lg', SidePanelSize::Large->value);
	Assert::same('extreme', SidePanelSize::Extreme->value);
	Assert::same('full', SidePanelSize::Full->value);
	Assert::same('full-except-menu', SidePanelSize::FullExceptMenu->value);
	Assert::count(6, SidePanelSize::cases());
});


test('nazev bloku formulare', function () {
	Assert::same('row', BlockNameEnum::ROW->value);
	Assert::same('row', BlockNameEnum::ROW->getName());
	Assert::type(BlockName::class, BlockNameEnum::ROW);
});
