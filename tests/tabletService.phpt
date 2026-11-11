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
 * Odeslání formuláře do tabletu: push a záložní dotazování čekací stránky (docs/tablets.md).
 * S dotazováním formulář čeká i na tablet bez notifikací a obsluha se dozví, jestli na něm
 * čekací stránka svítí; bez dotazování (pollInterval 0) platí, že bez pushe se neposílá.
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


/** sendForm(), markSeen() ani isWaitingPageOpen() se na tablety do DB neptají */
final class UnusedTabletQueryFactory implements TabletQueryFactory
{
	public function create(): TabletQuery
	{
		throw new LogicException('Dotaz na tablety se v testu nečeká.');
	}
}


const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc';
const FORM_URL = '/klient/formular/123';

function tabletService(FakeWebPushSender $sender, int $pollInterval): TabletService
{
	return new TabletService(
		new TestEntityManager(),
		new UnusedTabletQueryFactory(),
		$sender,
		new TestTranslator(),
		new Request(new UrlScript('https://localhost/klient/tablet')),
		new TestHttpResponse(),
		pollInterval: $pollInterval,
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

	Assert::null(tabletService($sender, 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([ENDPOINT], $sender->sent);
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
	Assert::same('Souhlas', $tablet->getPendingFormTitle());
	Assert::equal($now->modify(TabletService::PENDING_FORM_LIFETIME), $tablet->getPendingFormExpiresAt());
});


test('bez notifikací, čekací stránka svítí - odesláno, vyzvedne si ho dotazováním', function () use ($now) {
	$sender = new FakeWebPushSender();
	$tablet = pairedTablet(null, $now->modify('-8 seconds'));

	Assert::null(tabletService($sender, 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([], $sender->sent);
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
});


test('bez notifikací, čekací stránka nesvítí - hláška, formulář ale čeká dál', function () use ($now) {
	$tablet = pairedTablet(null, $now->modify('-5 minutes'));

	Assert::same(
		'fcadmin.tablets.messages.waitingPageClosed',
		tabletService(new FakeWebPushSender(), 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
});


test('odběr už neplatí - smaže se a formulář převezme dotazování', function () use ($now) {
	$sender = new FakeWebPushSender(result: false);
	$tablet = pairedTablet(ENDPOINT, $now->modify('-3 seconds'));

	Assert::null(tabletService($sender, 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::null($tablet->getPushEndpoint());
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());

	// Stejná situace se zhasnutým tabletem: obsluha se dozví, že má otevřít čekací stránku
	$tablet = pairedTablet(ENDPOINT, $now->modify('-1 hour'));
	Assert::same(
		'fcadmin.tablets.messages.waitingPageClosed',
		tabletService(new FakeWebPushSender(result: false), 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
	Assert::same(FORM_URL, $tablet->getPendingFormUrl());
});


test('push spadl nebo není nastavený klíč VAPID - s dotazováním to nevadí', function () use ($now) {
	$tablet = pairedTablet(ENDPOINT, $now->modify('-3 seconds'));
	$failing = new FakeWebPushSender(result: new WebPushException('Push služba neodpovídá.'));
	Assert::null(tabletService($failing, 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same(ENDPOINT, $tablet->getPushEndpoint()); // dočasná chyba odběr neruší

	$tablet = pairedTablet(ENDPOINT, $now->modify('-3 seconds'));
	$unconfigured = new FakeWebPushSender(configured: false);
	Assert::null(tabletService($unconfigured, 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([], $unconfigured->sent);
});


test('nespárovaný tablet formulář nedostane ani s dotazováním', function () use ($now) {
	$tablet = (new TestTablet())->setName('Recepce');

	Assert::same(
		'fcadmin.tablets.messages.notificationsDisabled',
		tabletService(new FakeWebPushSender(), 5)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
	Assert::null($tablet->getPendingFormUrl());
});


test('bez dotazování (pollInterval 0) platí původní chování - jen push', function () use ($now) {
	// bez notifikací se neposílá, i kdyby tablet před chvílí otevřel čekací stránku
	$tablet = pairedTablet(null, $now->modify('-2 seconds'));
	Assert::same(
		'fcadmin.tablets.messages.notificationsDisabled',
		tabletService(new FakeWebPushSender(), 0)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
	Assert::null($tablet->getPendingFormUrl());

	// neplatný odběr: smazat odběr i čekající formulář
	$tablet = pairedTablet(ENDPOINT);
	Assert::same(
		'fcadmin.tablets.messages.subscriptionExpired',
		tabletService(new FakeWebPushSender(result: false), 0)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
	Assert::null($tablet->getPushEndpoint());
	Assert::null($tablet->getPendingFormUrl());

	// chybí klíč VAPID
	$tablet = pairedTablet(ENDPOINT);
	Assert::same(
		'fcadmin.tablets.messages.pushNotConfigured',
		tabletService(new FakeWebPushSender(configured: false), 0)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);

	// chyba push služby
	$tablet = pairedTablet(ENDPOINT);
	Assert::same(
		'fcadmin.tablets.messages.sendFailed',
		tabletService(new FakeWebPushSender(result: new WebPushException('Chyba.')), 0)->sendForm($tablet, FORM_URL, 'Souhlas', $now),
	);
});


test('čekací stránka je otevřená, dokud se ptá - tři rozestupy a rezerva', function () use ($now) {
	$service = tabletService(new FakeWebPushSender(), 5);

	Assert::true($service->isWaitingPageOpen(pairedTablet(null, $now->modify('-25 seconds')), $now));
	Assert::false($service->isWaitingPageOpen(pairedTablet(null, $now->modify('-26 seconds')), $now));
	Assert::false($service->isWaitingPageOpen(pairedTablet(), $now)); // ještě se neozval

	// nespárovaný tablet a vypnuté dotazování
	$unpaired = (new TestTablet())->setName('Recepce')->setLastSeenAt($now);
	Assert::false($service->isWaitingPageOpen($unpaired, $now));
	Assert::false(tabletService(new FakeWebPushSender(), 0)->isWaitingPageOpen(pairedTablet(null, $now), $now));
});


test('dotaz čekací stránky zapíše čas nejvýš jednou za rozestup', function () use ($now) {
	$service = tabletService(new FakeWebPushSender(), 5);

	$tablet = pairedTablet();
	$service->markSeen($tablet, $now);
	Assert::equal($now, $tablet->getLastSeenAt());

	// za 2 s znovu - čas se nemění, do DB se nepíše
	$service->markSeen($tablet, $now->modify('+2 seconds'));
	Assert::equal($now, $tablet->getLastSeenAt());

	$service->markSeen($tablet, $now->modify('+5 seconds'));
	Assert::equal($now->modify('+5 seconds'), $tablet->getLastSeenAt());
});
