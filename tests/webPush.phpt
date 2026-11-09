<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\WebPush\WebPushSender;
use Tester\Assert;

/**
 * Web Push (VAPID): token JWT ES256 ověřitelný veřejným klíčem, který dostane prohlížeč,
 * a odběr jen na známé push služby (adresu posílá prohlížeč - ochrana před SSRF).
 */

require __DIR__ . '/bootstrap.php';


test('token VAPID je JWT ES256 ověřitelný veřejným klíčem', function () {
	$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
	openssl_pkey_export($key, $pem);
	$sender = new WebPushSender(base64_encode($pem), 'mailto:test@example.com');
	Assert::true($sender->isConfigured());

	$publicKey = base64_decode(strtr($sender->getPublicKey(), '-_', '+/'));
	Assert::same(65, strlen($publicKey)); // nekomprimovaný bod P-256
	Assert::same("\x04", $publicKey[0]);

	$jwt = $sender->createJwt('https://fcm.googleapis.com/fcm/send/abc', 1_800_000_000);
	[$header, $claims, $signature] = explode('.', $jwt);
	$decode = static fn(string $part): string => (string) base64_decode(strtr($part, '-_', '+/'));
	Assert::same(['typ' => 'JWT', 'alg' => 'ES256'], json_decode($decode($header), true));
	Assert::same(['aud' => 'https://fcm.googleapis.com', 'exp' => 1_800_000_000 + 12 * 3600, 'sub' => 'mailto:test@example.com'], json_decode($decode($claims), true));

	// Raw podpis r || s zpět do DER a ověření veřejným klíčem
	$raw = $decode($signature);
	Assert::same(64, strlen($raw));
	$der = '';
	foreach (str_split($raw, 32) as $integer) {
		$integer = ltrim($integer, "\0");
		$integer = ord($integer[0]) > 0x7F ? "\0" . $integer : $integer;
		$der .= "\x02" . chr(strlen($integer)) . $integer;
	}
	$der = "\x30" . chr(strlen($der)) . $der;
	Assert::same(1, openssl_verify($header . '.' . $claims, $der, openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256));
});


test('odběr jen https na push službu prohlížeče', function () {
	Assert::true(WebPushSender::isValidEndpoint('https://fcm.googleapis.com/fcm/send/abc'));
	Assert::true(WebPushSender::isValidEndpoint('https://web.push.apple.com/QK'));
	Assert::true(WebPushSender::isValidEndpoint('https://updates.push.services.mozilla.com/wpush/v2/x'));
	Assert::false(WebPushSender::isValidEndpoint('http://fcm.googleapis.com/fcm/send/abc'));
	Assert::false(WebPushSender::isValidEndpoint('https://intranet.local/x'));
	Assert::false(WebPushSender::isValidEndpoint('https://evilpush.apple.com.attacker.test/x'));
	Assert::false(WebPushSender::isValidEndpoint('https://fcm.googleapis.com/' . str_repeat('a', 2048)));
});


test('bez klíče není push nastavený', function () {
	Assert::false((new WebPushSender('', ''))->isConfigured());
});
