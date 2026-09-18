<?php

declare(strict_types=1);

use ADT\FancyAdmin\DI\FancyAdminExtension;
use ADT\FancyAdmin\Model\Entities\Passkey;
use ADT\FancyAdmin\Model\Entities\Traits\HasPasskeys;
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


test('projekt bez entity Identity kontrolu nezablokuje', function () {
	// appDir se nemusi podarit najit (dev checkout, path repository) - v takovem pripade
	// se ma jen preskocit, ne shodit kompilaci kontejneru
	Assert::null(FancyAdminExtension::findIdentityWithoutPasskeys([]));
});
