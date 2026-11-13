<?php

declare(strict_types=1);

use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletTrait;
use ADT\FancyAdmin\Model\Queries\Factories\TabletQueryFactory;
use ADT\FancyAdmin\Model\Queries\TabletQuery;
use ADT\FancyAdmin\Model\Tablet\TabletService;
use ADT\FancyAdmin\Model\WebPush\WebPushException;
use ADT\FancyAdmin\Model\WebPush\WebPushSender;
use ADT\FancyAdmin\Tests\Fixtures\TestEntityManager;
use ADT\FancyAdmin\Tests\Fixtures\TestHttpResponse;
use ADT\FancyAdmin\Tests\Fixtures\TestTranslator;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;
use Tracy\Debugger;

/**
 * Odeslání formuláře do tabletu (docs/tablets.md): formulář čeká u serveru na čekací stránku,
 * push tablet jen probudí. Čeká i tehdy, když push neodešel - obsluha se dozví, že má na
 * tabletu čekací stránku otevřít nebo obnovit. Service worker se na formulář jen dívá.
 */

require __DIR__ . '/bootstrap.php';


final class TestTablet implements Tablet
{
	use Identifier;
	use TabletTrait;
}


/** Push služba prohlížeče - odpověď určuje test, na síť se nejde */
final class FakeWebPushSender extends WebPushSender
{
	/** @var list<string> */
	public array $sent = [];

	public function __construct(
		private readonly bool $configured = true,
		private readonly bool|\Throwable $result = true,
	) {
	}

	public function isConfigured(): bool
	{
		return $this->configured;
	}

	public function send(string $endpoint): bool
	{
		$this->sent[] = $endpoint;
		if ($this->result instanceof \Throwable) {
			throw $this->result;
		}

		return $this->result;
	}
}


/** sendForm() ani markSeen() se na tablety do DB neptají */
final class UnusedTabletQueryFactory implements TabletQueryFactory
{
	public function create(): TabletQuery
	{
		throw new LogicException('Dotaz na tablety se v testu nečeká.');
	}
}


const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc';
const FORM_URL = '/klient/formular/123';

function tabletService(FakeWebPushSender $sender): TabletService
{
	return new TabletService(
		new TestEntityManager(),
		new UnusedTabletQueryFactory(),
		$sender,
		new TestTranslator(),
		new Request(new UrlScript('https://localhost/klient/tablet')),
		new TestHttpResponse(),
	);
}

function pairedTablet(?string $endpoint = null, ?DateTimeImmutable $lastSeenAt = null): TestTablet
{
	$tablet = (new TestTablet())->setName('Recepce');
	$tablet->pair(str_repeat('a', 64), new DateTimeImmutable('2026-01-01 08:00:00'));
	$tablet->setPushEndpoint($endpoint);
	if ($lastSeenAt !== null) {
		$tablet->setLastSeenAt($lastSeenAt);
	}

	return $tablet;
}

$now = new DateTimeImmutable('2026-01-01 12:00:00');

// Chyby push služby jdou do logu (Debugger::log)
$logDir = sys_get_temp_dir() . '/fancyadmin-tests/' . getmypid() . '/tablet-service';
@mkdir($logDir, 0777, recursive: true);
Tester\Helpers::purge($logDir);
Debugger::$logDirectory = $logDir;


test('push odešel - odesláno, formulář čeká na vyzvednutí', function () use ($now) {
	$sender = new FakeWebPushSender();
	$tablet = pairedTablet(ENDPOINT);

	Assert::null(tabletService($sender)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([ENDPOINT], $sender->sent);
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
	Assert::same('Souhlas', $tablet->getPendingFormTitle());
	Assert::equal($now->modify(TabletService::PENDING_FORM_LIFETIME), $tablet->getPendingFormExpiresAt());
});


test('nahlédnutí formulář nevyzvedne, vyzvednutí ho vydá jen jednou', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet(ENDPOINT);
	$service->sendForm($tablet, FORM_URL, 'Souhlas', $now);
	$form = ['url' => FORM_URL, 'title' => 'Souhlas'];

	// service worker po push zprávě: podle formuláře napíše notifikaci, formulář čeká dál
	Assert::same($form, $service->peekPendingForm($tablet, $now));
	Assert::same($form, $service->peekPendingForm($tablet, $now));
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());

	// čekací stránka
	Assert::same($form, $service->takePendingForm($tablet, $now));
	Assert::null($service->peekPendingForm($tablet, $now));
	Assert::null($service->takePendingForm($tablet, $now));
});


test('propadlý formulář se nevydá a vyzvednutí ho uklidí', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet(ENDPOINT);
	$service->sendForm($tablet, FORM_URL, 'Souhlas', $now);
	$later = $now->modify(TabletService::PENDING_FORM_LIFETIME);

	Assert::null($service->peekPendingForm($tablet, $later));
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
	Assert::null($service->takePendingForm($tablet, $later));
	Assert::null($tablet->getPendingFormUrl());
});


test('bez notifikací formulář čeká na obnovení čekací stránky - obsluha dostane hlášku', function () use ($now) {
	$sender = new FakeWebPushSender();
	$tablet = pairedTablet();

	Assert::same('fcadmin.tablets.messages.notNotified', tabletService($sender)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([], $sender->sent);
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
	Assert::same(['url' => FORM_URL, 'title' => 'Souhlas'], tabletService($sender)->takePendingForm($tablet, $now));
});


test('odběr už neplatí - smaže se, formulář čeká dál', function () use ($now) {
	$tablet = pairedTablet(ENDPOINT);

	Assert::same(
		'fcadmin.tablets.messages.notNotified',
		tabletService(new FakeWebPushSender(result: false))->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
	Assert::null($tablet->getPushEndpoint());
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
});


test('push spadl nebo není nastavený klíč VAPID - formulář čeká dál, odběr zůstává', function () use ($now) {
	$tablet = pairedTablet(ENDPOINT);
	$failing = new FakeWebPushSender(result: new WebPushException('Push služba neodpovídá.'));
	Assert::same('fcadmin.tablets.messages.notNotified', tabletService($failing)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same(ENDPOINT, $tablet->getPushEndpoint()); // dočasná chyba odběr neruší
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());

	$tablet = pairedTablet(ENDPOINT);
	$unconfigured = new FakeWebPushSender(configured: false);
	Assert::same('fcadmin.tablets.messages.notNotified', tabletService($unconfigured)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([], $unconfigured->sent);
	Assert::same(ENDPOINT, $tablet->getPushEndpoint());
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
});


test('nespárovaný tablet formulář nedostane', function () use ($now) {
	$sender = new FakeWebPushSender();
	$tablet = (new TestTablet())->setName('Recepce');

	Assert::same('fcadmin.tablets.messages.notPaired', tabletService($sender)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([], $sender->sent);
	Assert::null($tablet->getPendingFormUrl());
});


test('dotaz čekací stránky zapíše, kdy se tablet naposledy ozval', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet();

	$service->markSeen($tablet, $now);
	Assert::equal($now, $tablet->getLastSeenAt());

	$service->markSeen($tablet, $now->modify('+2 seconds'));
	Assert::equal($now->modify('+2 seconds'), $tablet->getLastSeenAt());
});
