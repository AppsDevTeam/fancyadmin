<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Push\MercurePush;
use ADT\FancyAdmin\Model\Push\NullPush;
use Tester\Assert;

/**
 * Odběr pushe (Mercure): token smí odebírat jen topiky stránky s prefixem projektu - hub sdílí
 * víc projektů a uživatel nesmí dostávat cizí změny.
 */

require __DIR__ . '/bootstrap.php';


$push = new MercurePush(
	publishUrl: 'http://hub.test/.well-known/mercure',
	subscribeUrl: '/.well-known/mercure',
	publisherKey: 'publisher-key',
	subscriberKey: 'subscriber-key',
	topicPrefix: 'https://dent.test/smile/',
	issuer: 'https://dent.test',
	audience: 'https://dent.test/.well-known/mercure',
);

$decode = static fn(string $part): array => json_decode(base64_decode(strtr($part, '-_', '+/')), true);


test('odběr má topiky s prefixem projektu a token jen na ně', function () use ($push, $decode) {
	$subscription = $push->subscription(['calendar', 'user/5']);

	Assert::same('/.well-known/mercure', $subscription['url']);
	Assert::same(['https://dent.test/smile/calendar', 'https://dent.test/smile/user/5'], $subscription['topics']);

	[$header, $claims, $signature] = explode('.', $subscription['token']);
	Assert::same(['alg' => 'HS256', 'typ' => 'at+jwt'], $decode($header));
	$claims = $decode($claims);
	Assert::same(['subscribe'], $claims['authorization_details'][0]['actions']);
	Assert::same(
		[['match' => 'https://dent.test/smile/calendar'], ['match' => 'https://dent.test/smile/user/5']],
		$claims['authorization_details'][0]['topics'],
	);
	Assert::same('https://dent.test', $claims['iss']);
	Assert::true($claims['exp'] > time());

	$expected = rtrim(strtr(base64_encode(hash_hmac('sha256', strstr($subscription['token'], '.' . $signature, true), 'subscriber-key', true)), '+/', '-_'), '=');
	Assert::same($expected, $signature, 'podepsáno klíčem odběratele');
});


test('bez topiků není odběr', function () use ($push) {
	Assert::null($push->subscription([]));
});


test('bez hubu se nic neodebírá ani neposílá', function () {
	$push = new NullPush();
	$push->publish('calendar');
	Assert::null($push->subscription(['calendar']));
});
