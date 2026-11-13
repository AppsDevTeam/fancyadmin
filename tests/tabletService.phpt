<?php

declare(strict_types=1);

use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Enums\TabletSignRequestState;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use ADT\FancyAdmin\Model\Entities\TabletSignRequestTrait;
use ADT\FancyAdmin\Model\Entities\TabletTrait;
use ADT\FancyAdmin\Model\Queries\Factories\TabletQueryFactory;
use ADT\FancyAdmin\Model\Queries\TabletQuery;
use ADT\FancyAdmin\Model\Tablet\TabletService;
use ADT\FancyAdmin\Model\Tablet\TabletSignRequests;
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
 * Odeslání formuláře do tabletu (docs/tablets.md): každé odeslání je žádost o podpis ve vlastní
 * tabulce - tablet podle ní pozná, co má otevřít, a zůstává jako evidence. Push tablet jen
 * probudí; žádost čeká i tehdy, když push neodešel, a obsluha se dozví, že má na tabletu čekací
 * stránku otevřít nebo obnovit. Service worker se na žádost jen dívá.
 */

require __DIR__ . '/bootstrap.php';


final class TestTablet implements Tablet
{
	use Identifier;
	use TabletTrait;
}


final class TestSignRequest implements TabletSignRequest
{
	use Identifier;
	use TabletSignRequestTrait;
}


/** Žádosti o podpis v paměti - stejná pravidla výběru jako DoctrineTabletSignRequests */
final class MemorySignRequests implements TabletSignRequests
{
	/** @var list<TestSignRequest> v pořadí založení */
	public array $all = [];

	public function create(Tablet $tablet, string $url, string $title, DateTimeImmutable $now, DateTimeImmutable $expiresAt): TabletSignRequest
	{
		return TestSignRequest::create($tablet, $url, $title, $now, $expiresAt);
	}

	public function add(TabletSignRequest $request): void
	{
		$this->all[] = $request;
	}

	public function findPending(Tablet $tablet, DateTimeImmutable $now): ?TabletSignRequest
	{
		return $this->newest($tablet, fn (TestSignRequest $r): bool => $r->getOpenedAt() === null && $r->getExpiresAt() > $now);
	}

	public function findOpen(Tablet $tablet): ?TabletSignRequest
	{
		return $this->newest($tablet, fn (TestSignRequest $r): bool => $r->getOpenedAt() !== null);
	}

	public function findUnfinished(Tablet $tablet, DateTimeImmutable $now): array
	{
		return array_values(array_filter(
			$this->all,
			fn (TestSignRequest $r): bool => $this->isUnfinished($r, $tablet) && ($r->getOpenedAt() !== null || $r->getExpiresAt() > $now),
		));
	}

	private function newest(Tablet $tablet, callable $filter): ?TabletSignRequest
	{
		foreach (array_reverse($this->all) as $request) {
			if ($this->isUnfinished($request, $tablet) && $filter($request)) {
				return $request;
			}
		}

		return null;
	}

	private function isUnfinished(TestSignRequest $request, Tablet $tablet): bool
	{
		return $request->getTablet() === $tablet && $request->getSignedAt() === null && $request->getCancelledAt() === null;
	}
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

function tabletService(FakeWebPushSender $sender, ?MemorySignRequests $requests = null): TabletService
{
	return new TabletService(
		new TestEntityManager(),
		new UnusedTabletQueryFactory(),
		$sender,
		new TestTranslator(),
		new Request(new UrlScript('https://localhost/klient/tablet')),
		new TestHttpResponse(),
		$requests ?? new MemorySignRequests(),
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


test('push odešel - odesláno, žádost čeká na vyzvednutí', function () use ($now) {
	$sender = new FakeWebPushSender();
	$requests = new MemorySignRequests();
	$tablet = pairedTablet(ENDPOINT);

	$result = tabletService($sender, $requests)->send($tablet, FORM_URL, 'Souhlas', null, $now);

	Assert::true($result->isNotified());
	Assert::null($result->message);
	Assert::same([ENDPOINT], $sender->sent);
	Assert::same([$result->request], $requests->all);
	Assert::same($tablet, $result->request->getTablet());
	Assert::same('Recepce', $result->request->getTabletName());
	Assert::same(FORM_URL, $result->request->getUrl());
	Assert::same('Souhlas', $result->request->getTitle());
	Assert::equal($now, $result->request->getCreatedAt());
	Assert::equal($now->modify(TabletService::PENDING_FORM_LIFETIME), $result->request->getExpiresAt());
	Assert::same(TabletSignRequestState::Pending, $result->request->getState($now));
});


test('žádost nese, co se podepisuje a kdo ji poslal - nezávisle na entitách projektu', function () use ($now) {
	$tablet = pairedTablet(ENDPOINT);

	$request = tabletService(new FakeWebPushSender())->send(
		$tablet,
		FORM_URL,
		'Smlouva',
		fn (TabletSignRequest $request) => $request
			->setSubject('order', '4321', ['client' => 17])
			->setRequester('5', 'Jana Nováková'),
		$now,
	)->request;

	Assert::same('order', $request->getType());
	Assert::same('4321', $request->getSubjectId());
	Assert::same(['client' => 17], $request->getData());
	Assert::same('5', $request->getRequesterId());
	Assert::same('Jana Nováková', $request->getRequesterName());
});


test('nahlédnutí žádost nevyzvedne, vyzvednutí ji vydá jen jednou a otevře', function () use ($now) {
	$requests = new MemorySignRequests();
	$service = tabletService(new FakeWebPushSender(), $requests);
	$tablet = pairedTablet(ENDPOINT);
	$request = $service->send($tablet, FORM_URL, 'Souhlas', null, $now)->request;
	$form = ['url' => FORM_URL, 'title' => 'Souhlas'];

	// service worker po push zprávě: podle žádosti napíše notifikaci, žádost čeká dál
	Assert::same($form, $service->peekPendingForm($tablet, $now));
	Assert::same($form, $service->peekPendingForm($tablet, $now));
	Assert::null($request->getOpenedAt());
	Assert::null($service->getOpenRequest($tablet));

	// čekací stránka
	$opened = $now->modify('+20 seconds');
	Assert::same($form, $service->takePendingForm($tablet, $opened));
	Assert::equal($opened, $request->getOpenedAt());
	Assert::same(TabletSignRequestState::Opened, $request->getState($opened));
	Assert::same($request, $service->getOpenRequest($tablet));
	Assert::null($service->peekPendingForm($tablet, $opened));
	Assert::null($service->takePendingForm($tablet, $opened));
});


test('propadlá žádost se nevydá a zůstává v evidenci jako propadlá', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet(ENDPOINT);
	$request = $service->send($tablet, FORM_URL, 'Souhlas', null, $now)->request;
	$later = $now->modify(TabletService::PENDING_FORM_LIFETIME);

	Assert::null($service->peekPendingForm($tablet, $later));
	Assert::null($service->takePendingForm($tablet, $later));
	Assert::null($request->getOpenedAt());
	Assert::null($request->getCancelledAt());
	Assert::same(TabletSignRequestState::Expired, $request->getState($later));
});


test('podpis žádost vyřídí - tablet už nic nezobrazuje a návrat na čekací stránku ji neruší', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet(ENDPOINT);
	$request = $service->send($tablet, FORM_URL, 'Souhlas', null, $now)->request;
	$service->takePendingForm($tablet, $now);

	$signed = $now->modify('+2 minutes');
	$service->finish($request, $signed);

	Assert::equal($signed, $request->getSignedAt());
	Assert::same(TabletSignRequestState::Signed, $request->getState($signed));
	Assert::null($service->getOpenRequest($tablet));

	$service->returnToWaiting($tablet, $signed->modify('+10 seconds'));
	Assert::null($request->getCancelledAt());
});


test('návrat na čekací stránku otevřenou žádost zruší a dá vědět projektu', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$released = [];
	$service->onRelease[] = function (Tablet $tablet) use (&$released) {
		$released[] = $tablet;
	};
	$tablet = pairedTablet(ENDPOINT);
	$request = $service->send($tablet, FORM_URL, 'Souhlas', null, $now)->request;
	$service->takePendingForm($tablet, $now);

	// zákazník odešel, tablet se po nečinnosti vrátil na čekací stránku
	$back = $now->modify('+5 minutes');
	$service->returnToWaiting($tablet, $back);

	Assert::equal($back, $request->getCancelledAt());
	Assert::same(TabletSignRequestState::Cancelled, $request->getState($back));
	Assert::null($service->getOpenRequest($tablet));
	Assert::same([$tablet], $released);
	Assert::equal($back, $tablet->getLastSeenAt());
});


test('načtení čekací stránky nezruší žádost, která na vyzvednutí teprve čeká', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$released = 0;
	$service->onRelease[] = function () use (&$released) {
		$released++;
	};
	$tablet = pairedTablet();
	$request = $service->send($tablet, FORM_URL, 'Souhlas', null, $now)->request;

	// obsluha na tabletu obnovila čekací stránku - ta si žádost hned potom vyzvedne
	$service->returnToWaiting($tablet, $now->modify('+30 seconds'));

	Assert::null($request->getCancelledAt());
	Assert::same(0, $released);
	Assert::same(['url' => FORM_URL, 'title' => 'Souhlas'], $service->takePendingForm($tablet, $now->modify('+31 seconds')));
});


test('nová žádost zruší předchozí nevyřízené žádosti tabletu, vyřízené nechá', function () use ($now) {
	$requests = new MemorySignRequests();
	$service = tabletService(new FakeWebPushSender(), $requests);
	$tablet = pairedTablet(ENDPOINT);
	$other = pairedTablet(ENDPOINT);

	$signed = $service->send($tablet, '/klient/formular/1', 'První', null, $now)->request;
	$service->takePendingForm($tablet, $now);
	$service->finish($signed, $now);

	$open = $service->send($tablet, '/klient/formular/2', 'Druhý', null, $now)->request;
	$service->takePendingForm($tablet, $now);
	$elsewhere = $service->send($other, '/klient/formular/3', 'Jinde', null, $now)->request;

	$later = $now->modify('+1 minute');
	$new = $service->send($tablet, '/klient/formular/4', 'Nový', null, $later)->request;

	Assert::null($signed->getCancelledAt());
	Assert::equal($later, $open->getCancelledAt());
	Assert::null($elsewhere->getCancelledAt());
	Assert::null($new->getCancelledAt());
	Assert::same(['url' => '/klient/formular/4', 'title' => 'Nový'], $service->peekPendingForm($tablet, $later));
	Assert::count(4, $requests->all);
});


test('odpojení tabletu zruší čekající i otevřenou žádost', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet(ENDPOINT);
	$request = $service->send($tablet, FORM_URL, 'Souhlas', null, $now)->request;

	$service->disconnect($tablet, $now->modify('+1 minute'));

	Assert::equal($now->modify('+1 minute'), $request->getCancelledAt());
	Assert::false($tablet->isPaired());
});


test('bez notifikací žádost čeká na obnovení čekací stránky - obsluha dostane hlášku', function () use ($now) {
	$sender = new FakeWebPushSender();
	$service = tabletService($sender);
	$tablet = pairedTablet();

	$result = $service->send($tablet, FORM_URL, 'Souhlas', null, $now);

	Assert::false($result->isNotified());
	Assert::same('fcadmin.tablets.messages.notNotified', $result->message);
	Assert::same([], $sender->sent);
	Assert::same(['url' => FORM_URL, 'title' => 'Souhlas'], $service->takePendingForm($tablet, $now));
});


test('odběr už neplatí - smaže se, žádost čeká dál', function () use ($now) {
	$service = tabletService(new FakeWebPushSender(result: false));
	$tablet = pairedTablet(ENDPOINT);

	Assert::same('fcadmin.tablets.messages.notNotified', $service->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::null($tablet->getPushEndpoint());
	Assert::same(['url' => FORM_URL, 'title' => 'Souhlas'], $service->peekPendingForm($tablet, $now));
});


test('push spadl nebo není nastavený klíč VAPID - žádost čeká dál, odběr zůstává', function () use ($now) {
	$tablet = pairedTablet(ENDPOINT);
	$failing = tabletService(new FakeWebPushSender(result: new WebPushException('Push služba neodpovídá.')));
	Assert::same('fcadmin.tablets.messages.notNotified', $failing->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same(ENDPOINT, $tablet->getPushEndpoint()); // dočasná chyba odběr neruší
	Assert::same(['url' => FORM_URL, 'title' => 'Souhlas'], $failing->peekPendingForm($tablet, $now));

	$tablet = pairedTablet(ENDPOINT);
	$sender = new FakeWebPushSender(configured: false);
	$unconfigured = tabletService($sender);
	Assert::same('fcadmin.tablets.messages.notNotified', $unconfigured->sendForm($tablet, FORM_URL, 'Souhlas', $now));
	Assert::same([], $sender->sent);
	Assert::same(ENDPOINT, $tablet->getPushEndpoint());
	Assert::same(['url' => FORM_URL, 'title' => 'Souhlas'], $unconfigured->peekPendingForm($tablet, $now));
});


test('nespárovaný tablet žádost nedostane', function () use ($now) {
	$sender = new FakeWebPushSender();
	$requests = new MemorySignRequests();
	$tablet = (new TestTablet())->setName('Recepce');

	$result = tabletService($sender, $requests)->send($tablet, FORM_URL, 'Souhlas', null, $now);

	Assert::null($result->request);
	Assert::false($result->isNotified());
	Assert::same('fcadmin.tablets.messages.notPaired', $result->message);
	Assert::same([], $sender->sent);
	Assert::same([], $requests->all);
});


test('dotaz čekací stránky zapíše, kdy se tablet naposledy ozval', function () use ($now) {
	$service = tabletService(new FakeWebPushSender());
	$tablet = pairedTablet();

	$service->markSeen($tablet, $now);
	Assert::equal($now, $tablet->getLastSeenAt());

	$service->markSeen($tablet, $now->modify('+2 seconds'));
	Assert::equal($now->modify('+2 seconds'), $tablet->getLastSeenAt());
});
