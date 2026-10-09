<?php

declare(strict_types=1);

use ADT\DoctrineLoggable\Attributes\LoggableProperty;
use ADT\FancyAdmin\Model\Attributes\AuditedValue;
use ADT\FancyAdmin\Model\Entities\AclRoleTrait;
use ADT\FancyAdmin\Model\Entities\AclTrait;
use ADT\FancyAdmin\Model\Entities\ConfigurationTrait;
use ADT\FancyAdmin\Model\Entities\IdentityTrait;
use ADT\FancyAdmin\Model\Entities\SsoTrait;
use Tester\Assert;

/**
 * Co z entitnich trait konci v logu a co z toho i v auditni stope.
 *
 * Atributy jsou tichy kontrakt: chybejici #[LoggableProperty] znamena, ze se zmena
 * nezaloguje vubec, a naopak #[AuditedValue] na nespravne vlastnosti dostane data
 * do stopy s mnohem delsi retenci, nez maji provozni tabulky. Ani jedno neni videt
 * jinak nez odsud.
 */

require __DIR__ . '/bootstrap.php';


/** @return array{logged: bool, withValue: bool, audited: bool} */
function propertyLogging(string $trait, string $property): array
{
	$reflection = new ReflectionProperty($trait, $property);
	$loggable = $reflection->getAttributes(LoggableProperty::class);

	return [
		'logged' => (bool) $loggable,
		'withValue' => $loggable ? $loggable[0]->newInstance()->withValue : false,
		'audited' => (bool) $reflection->getAttributes(AuditedValue::class),
	];
}


test('hash hesla se loguje bez hodnoty', function () {
	// S hodnotou by change_log drzel historii hashu vcetne davno neplatnych hesel -
	// pri uniku dumpu material na offline lamani. Bez atributu by se naopak zmena
	// hesla nezalogovala vubec, a prave ta je ta informace, o kterou jde.
	$password = propertyLogging(IdentityTrait::class, 'password');

	Assert::true($password['logged']);
	Assert::false($password['withValue']);
	Assert::false($password['audited']);
});


test('osobni udaje zustavaji v change_logu, do auditu nejdou', function () {
	// Auditni stopa se archivuje mnohem dele nez provozni data, takze co do ni
	// jednou spadne, zustane tam i po smazani uctu.
	foreach (['firstName', 'lastName', 'phoneNumber'] as $property) {
		$logging = propertyLogging(IdentityTrait::class, $property);

		Assert::true($logging['logged'], $property);
		Assert::false($logging['audited'], $property);
	}
});


test('do auditu jde identita, opravneni a anonymizace', function () {
	foreach (['email', 'username', 'roles', 'sso', 'ssoSub', 'anonymizedAt', 'anonymizedBy'] as $property) {
		$logging = propertyLogging(IdentityTrait::class, $property);

		Assert::true($logging['logged'], $property);
		Assert::true($logging['withValue'], $property);
		Assert::true($logging['audited'], $property);
	}
});


test('u role jde do auditu rozsah opravneni, ne detaily heslove politiky', function () {
	foreach (['name', 'acls', 'isAdmin', 'needsSso', 'needs2fa', 'sessionExpirationMinutes'] as $property) {
		Assert::true(propertyLogging(AclRoleTrait::class, $property)['audited'], $property);
	}

	// zmena se zaznamena, ale hodnota zustane v change_logu - je to provozni detail
	foreach (['passwordMinLength', 'passwordRequireUppercase', 'passwordRequireDigit'] as $property) {
		$logging = propertyLogging(AclRoleTrait::class, $property);

		Assert::true($logging['logged'], $property);
		Assert::false($logging['audited'], $property);
	}
});


test('vazba role-resource jde do auditu cela', function () {
	// Je to samo o sobe zmena opravneni, jina hodnota tam neni.
	foreach (['role', 'resource'] as $property) {
		Assert::true(propertyLogging(AclTrait::class, $property)['audited'], $property);
	}
});


test('nastaveni poskytovatele identity jde do auditu s hodnotami', function () {
	// Prepsany baseUrl nebo clientId znamena presmerovani prihlasovani jinam.
	foreach (['realm', 'baseUrl', 'hostUrl', 'clientId', 'frontendClientId', 'defaultRole'] as $property) {
		Assert::true(propertyLogging(SsoTrait::class, $property)['audited'], $property);
	}
});


test('hodnota konfigurace do auditu nejde', function () {
	// Do sloupce se vejde cokoliv vcetne tajemstvi; ktera konfigurace se zmenila,
	// rekne identifikace zaznamu.
	$value = propertyLogging(ConfigurationTrait::class, 'value');

	Assert::true($value['logged']);
	Assert::false($value['audited']);
});
