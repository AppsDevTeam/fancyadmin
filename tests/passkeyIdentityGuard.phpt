<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\Model\Entities\Passkey;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
use FancyAdminTests\Fixtures\EntityScan\Model\Entities\ScanMarker;
use FancyAdminTests\Fixtures\EntityScan\Model\Entities\ScannedEntity;
use Nette\Loaders\RobotLoader;
use Tester\Assert;

/**
 * Kontrola, ze projektova Identity pri passkeyEnabled implementuje HasPasskeys.
 *
 * PasskeyService na tom interface stoji (user handle + kolekce klicu). Projekt, ktery si
 * kolekci passkeys namapoval rucne misto IdentityPasskeysTrait, projde i orm:validate-schema
 * a chyba se projevi az jako 500 pri registraci prvniho klice - proto se hlida uz pri
 * kompilaci kontejneru, stejne jako chybejici PasskeyQueryFactory nebo nezname rpId.
 */

require __DIR__ . '/bootstrap.php';

$entityScanAppDir = __DIR__ . '/fixtures/entityScan/app';

// Fixtury nejsou v composer autoloadu - findProjectEntityClasses() indexovane tridy jen
// filtruje pres class_exists(), nacist si je musi projekt sam (v praxi composer nad app/).
$fixtureLoader = new RobotLoader();
$fixtureLoader->addDirectory($entityScanAppDir);
$fixtureLoader->setTempDirectory(sys_get_temp_dir() . '/fancyadmin-tests-entity-scan');
$fixtureLoader->register();


class IdentityWithPasskeys implements HasPasskeys
{
	/** @return Passkey[] */
	public function getPasskeys(): array
	{
		return [];
	}

	public function getPasskeyUserHandle(): ?string
	{
		return null;
	}

	public function setPasskeyUserHandle(?string $passkeyUserHandle): static
	{
		return $this;
	}
}

class IdentityWithoutPasskeys
{
}

class IdentityWithPasskeysDescendant extends IdentityWithPasskeys
{
}


test('entita s HasPasskeys projde', function () {
	Assert::null(FancyAdminExtension::findIdentityWithoutPasskeys([IdentityWithPasskeys::class]));
});


test('entita bez HasPasskeys se vrati k nahlaseni', function () {
	Assert::same(
		IdentityWithoutPasskeys::class,
		FancyAdminExtension::findIdentityWithoutPasskeys([IdentityWithoutPasskeys::class])
	);
});


test('interface zdedeny od predka staci', function () {
	Assert::null(FancyAdminExtension::findIdentityWithoutPasskeys([IdentityWithPasskeysDescendant::class]));
});


test('hlasi se prvni nevyhovujici entita, i kdyz jich je vic', function () {
	Assert::same(
		IdentityWithoutPasskeys::class,
		FancyAdminExtension::findIdentityWithoutPasskeys([
			IdentityWithPasskeys::class,
			IdentityWithoutPasskeys::class,
		])
	);
});


test('bez nalezenych entit se nehlasi nic', function () {
	Assert::null(FancyAdminExtension::findIdentityWithoutPasskeys([]));
});


test('sken vrati jen instancovatelne entity s hledanym rozhranim', function () use ($entityScanAppDir) {
	// abstraktni predek ani trida bez rozhrani se vracet nesmi - jinak by guard hlasil
	// chybu na necem, co se nikdy nepouzije jako Identity
	Assert::same(
		[ScannedEntity::class],
		FancyAdminExtension::findProjectEntityClasses($entityScanAppDir, ScanMarker::class)
	);
});


test('nezname appDir kontrolu jen preskoci', function () {
	// dev checkout nebo path repository - nefunkcni detekce cesty nesmi shodit kompilaci
	Assert::same([], FancyAdminExtension::findProjectEntityClasses(null, ScanMarker::class));
});


test('appDir bez Model/Entities kontrolu jen preskoci', function () {
	Assert::same(
		[],
		FancyAdminExtension::findProjectEntityClasses(__DIR__ . '/fixtures/entityScanWithoutEntities/app', ScanMarker::class)
	);
});


test('sken se poskladany s kontrolou chova jako guard v beforeCompile', function () use ($entityScanAppDir) {
	// ScannedEntity je "Identity" bez HasPasskeys - presne pripad, kvuli kteremu guard vznikl
	Assert::same(
		ScannedEntity::class,
		FancyAdminExtension::findIdentityWithoutPasskeys(
			FancyAdminExtension::findProjectEntityClasses($entityScanAppDir, ScanMarker::class)
		)
	);
});
