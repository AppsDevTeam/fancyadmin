<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\SecretCipher;
use Tester\Assert;

/**
 * Sifrovani tajnych hodnot konfigurace (typ secret).
 *
 * V DB, zaloze i change_logu lezi jen zasifrovana hodnota - precist ji jde jen se
 * serverovym klicem, ktery v DB neni.
 */

require __DIR__ . '/bootstrap.php';


test('hodnota projde sifrovanim a desifrovanim beze zmeny', function () {
	$cipher = new SecretCipher('server-secret');

	$encrypted = $cipher->encrypt('sk-ant-test-123');

	Assert::true(SecretCipher::isEncrypted($encrypted));
	Assert::notContains('sk-ant-test-123', $encrypted);
	Assert::same('sk-ant-test-123', $cipher->decrypt($encrypted));
});


test('kazde sifrovani ma jiny nonce', function () {
	$cipher = new SecretCipher('server-secret');

	Assert::notSame($cipher->encrypt('stejna hodnota'), $cipher->encrypt('stejna hodnota'));
});


test('jiny klic hodnotu neprecte', function () {
	$encrypted = new SecretCipher('server-secret')->encrypt('tajne');

	Assert::exception(
		fn() => new SecretCipher('jiny-klic')->decrypt($encrypted),
		RuntimeException::class,
		'%a%jiným klíčem%a%',
	);
});


test('poskozena hodnota se odmitne', function () {
	$cipher = new SecretCipher('server-secret');
	$encrypted = $cipher->encrypt('tajne');

	// zmena jedineho znaku ciphertextu = autentizace selze
	$tampered = substr($encrypted, 0, -2) . (substr($encrypted, -2, 1) === 'A' ? 'B' : 'A') . substr($encrypted, -1);

	Assert::exception(fn() => $cipher->decrypt($tampered), RuntimeException::class);
	Assert::exception(fn() => $cipher->decrypt('secretbox:v1:nic'), RuntimeException::class);
});


test('nezasifrovana hodnota se nedesifruje', function () {
	Assert::false(SecretCipher::isEncrypted('sk-ant-plaintext'));
	Assert::false(SecretCipher::isEncrypted(null));

	Assert::exception(
		fn() => new SecretCipher('server-secret')->decrypt('sk-ant-plaintext'),
		RuntimeException::class,
	);
});


test('bez klice nejde sifrovat ani desifrovat', function () {
	foreach ([null, '', '  '] as $key) {
		$cipher = new SecretCipher($key);

		Assert::false($cipher->isConfigured());
		Assert::exception(fn() => $cipher->encrypt('tajne'), RuntimeException::class, '%a%fancyadmin.secretKey%a%');
	}
});
