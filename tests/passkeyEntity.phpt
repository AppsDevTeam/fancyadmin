<?php

declare(strict_types=1);

use ADT\FancyAdmin\Tests\Fixtures\TestIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestLazyPasskeyIdentity;
use ADT\FancyAdmin\Tests\Fixtures\TestPasskey;
use Tester\Assert;

/**
 * PasskeyTrait a IdentityPasskeysTrait - ulozeny WebAuthn klic.
 *
 * Binarni sloupce (credentialId, aaguid, userHandle) hydratuje DBAL podle verze bud
 * jako string, nebo jako stream - gettery to musi srovnat.
 */

require __DIR__ . '/bootstrap.php';


function toStream(string $data)
{
	$stream = fopen('php://memory', 'r+');
	fwrite($stream, $data);
	rewind($stream);
	return $stream;
}


test('klic patri identite a ma nazev', function () {
	$identity = new TestIdentity();
	$passkey = new TestPasskey($identity, 'Telefon');

	Assert::same($identity, $passkey->getIdentity());
	Assert::same('Telefon', $passkey->getName());

	$jina = new TestIdentity();
	Assert::same($jina, $passkey->setIdentity($jina)->getIdentity());
	Assert::same('Notebook', $passkey->setName('Notebook')->getName());
});


test('credential id se vraci jako retezec', function () {
	$passkey = new TestPasskey(new TestIdentity());

	Assert::same('raw-id', $passkey->setCredentialId('raw-id')->getCredentialId());
	Assert::same("\x00\x01\xFF", $passkey->setCredentialId("\x00\x01\xFF")->getCredentialId());
});


test('credential id ze streamu se precte cely a od zacatku', function () {
	$passkey = new TestPasskey(new TestIdentity());
	$stream = toStream("\x00binarni-id");
	fread($stream, 3); // ukazatel uz nekde je - getter ho musi vratit na zacatek

	$passkey->setRawCredentialId($stream);

	Assert::same("\x00binarni-id", $passkey->getCredentialId());
	// Druhe cteni musi dat totez.
	Assert::same("\x00binarni-id", $passkey->getCredentialId());
});


test('aaguid je nepovinny a taky zvlada stream', function () {
	$passkey = new TestPasskey(new TestIdentity());

	Assert::null($passkey->getAaguid());
	Assert::same('0123456789abcdef', $passkey->setAaguid('0123456789abcdef')->getAaguid());

	$passkey->setRawAaguid(toStream('0123456789abcdef'));
	Assert::same('0123456789abcdef', $passkey->getAaguid());

	Assert::null($passkey->setAaguid(null)->getAaguid());
});


test('verejny klic a citac podpisu', function () {
	$passkey = new TestPasskey(new TestIdentity());

	Assert::same(0, $passkey->getSignCount());
	Assert::same(5, $passkey->setSignCount(5)->getSignCount());
	Assert::same('-----BEGIN PUBLIC KEY-----X', $passkey->setPublicKey('-----BEGIN PUBLIC KEY-----X')->getPublicKey());
});


test('transports, zalohovatelnost a posledni pouziti jsou nepovinne', function () {
	$passkey = new TestPasskey(new TestIdentity());

	Assert::null($passkey->getTransports());
	Assert::null($passkey->getBackupEligible());
	Assert::null($passkey->getBackupState());
	Assert::null($passkey->getLastUsedAt());

	$lastUsedAt = new DateTimeImmutable('2026-04-01 12:00:00');
	$passkey->setTransports(['internal', 'hybrid'])->setBackupEligible(true)->setBackupState(false)->setLastUsedAt($lastUsedAt);

	Assert::same(['internal', 'hybrid'], $passkey->getTransports());
	Assert::true($passkey->getBackupEligible());
	Assert::false($passkey->getBackupState());
	Assert::same($lastUsedAt, $passkey->getLastUsedAt());
});


test('klic si pamatuje, kdy vznikl', function () {
	$passkey = new TestPasskey(new TestIdentity());
	$createdAt = new DateTimeImmutable('2026-02-02 08:00:00');

	Assert::same($createdAt, $passkey->setCreatedAt($createdAt)->getCreatedAt());
});


test('identita bez klicu vraci prazdne pole', function () {
	// Kolekce se inicializuje az pri prvnim cteni, takze cerstva entita nespadne.
	Assert::same([], new TestLazyPasskeyIdentity()->getPasskeys());
});


test('klice identity se vraci jako pole', function () {
	$identity = new TestIdentity();
	$prvni = new TestPasskey($identity, 'Telefon');
	$druhy = new TestPasskey($identity, 'Notebook');

	$identity->addPasskey($prvni)->addPasskey($druhy);

	Assert::same([$prvni, $druhy], $identity->getPasskeys());
});


test('user handle je nepovinny a zvlada stream', function () {
	// Autentikatoru se posila nahodny opaque handle, nikdy interni id identity.
	$identity = new TestIdentity();

	Assert::null($identity->getPasskeyUserHandle());

	$handle = random_bytes(32);
	Assert::same($handle, $identity->setPasskeyUserHandle($handle)->getPasskeyUserHandle());

	new ReflectionProperty($identity, 'passkeyUserHandle')->setValue($identity, toStream($handle));
	Assert::same($handle, $identity->getPasskeyUserHandle());

	Assert::null($identity->setPasskeyUserHandle(null)->getPasskeyUserHandle());
});
