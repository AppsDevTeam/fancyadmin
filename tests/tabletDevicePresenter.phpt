<?php

declare(strict_types=1);

use ADT\DoctrineComponents\Entities\Traits\Identifier;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletTrait;
use ADT\FancyAdmin\Model\Tablet\TabletService;
use ADT\FancyAdmin\Tests\Fixtures\TestHttpResponse;
use ADT\FancyAdmin\UI\Presenters\TabletDevice\TabletDevicePresenterTrait;
use Nette\Application\Request as AppRequest;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\Helpers;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * Čekací stránka tabletu: service worker volá signály na své vlastní adrese
 * ({link takeForm!} v sw.js.latte = sw?do=takeForm). Akce sw se při signálu musí přeskočit,
 * jinak worker místo JSON s formulářem dostane znovu svůj kód.
 */

require __DIR__ . '/bootstrap.php';


/** Tablet bez DB - cookie tabletu žádná, takže getCurrentTablet() vrací null */
final class NoTabletService extends TabletService
{
	public function __construct()
	{
	}

	public function getCurrentTablet(): ?Tablet
	{
		return null;
	}

	public function getCookiePath(): string
	{
		return '/klient/';
	}
}


final class PairedTestTablet implements Tablet
{
	use Identifier;
	use TabletTrait;
}


/** Spárovaný tablet bez DB - zaznamená, že se čekací stránka ohlásila (dotazování) */
final class PairedTabletService extends TabletService
{
	public int $seen = 0;

	public function __construct(private readonly Tablet $tablet)
	{
	}

	public function getCurrentTablet(): ?Tablet
	{
		return $this->tablet;
	}

	public function markSeen(Tablet $tablet, DateTimeImmutable $now = new DateTimeImmutable()): void
	{
		$this->seen++;
	}

	/** Dotaz na formulář není otevření čekací stránky - tablet se jím nesmí uvolnit ($onRelease) */
	public function returnToWaiting(Tablet $tablet, DateTimeImmutable $now = new DateTimeImmutable()): void
	{
		throw new LogicException('Signál čekací stránky nesmí tablet vracet na čekací stránku.');
	}

	public int $taken = 0;

	public function takePendingForm(Tablet $tablet, DateTimeImmutable $now = new DateTimeImmutable()): ?array
	{
		$this->taken++;

		return ['url' => '/klient/formular/1', 'title' => 'Souhlas'];
	}

	public function peekPendingForm(Tablet $tablet, DateTimeImmutable $now = new DateTimeImmutable()): ?array
	{
		return ['url' => '/klient/formular/1', 'title' => 'Souhlas'];
	}
}


final class TestTabletDevicePresenter extends Presenter
{
	use TabletDevicePresenterTrait;

	public function setTabletService(TabletService $tabletService): void
	{
		$this->_tabletService = $tabletService;
	}
}


test('signál takeForm na adrese workeru vrátí JSON, ne kód workeru', function () {
	$httpResponse = new TestHttpResponse();
	$presenter = new TestTabletDevicePresenter();
	// Cookie _nss = požadavek ze stejného webu (ochrana signálů proti CSRF)
	$presenter->injectPrimary(
		new Request(
			new UrlScript('https://localhost/klient/tablet/sw?do=takeForm'),
			cookies: [Helpers::StrictCookieName => '1'],
			method: 'POST',
		),
		$httpResponse,
	);
	$presenter->setTabletService(new NoTabletService());

	$response = $presenter->run(new AppRequest('TabletDevice', 'POST', ['action' => 'sw', 'do' => 'takeForm']));

	Assert::type(JsonResponse::class, $response);
	Assert::same(['ok' => false], $response->getPayload());
	Assert::same(400, $httpResponse->code);
	Assert::notContains('application/javascript', $httpResponse->headers['Content-Type'] ?? '');
});


/** takeForm! na čekací stránce: s poll=1 si formulář vyzvedává stránka, s peek=1 se service worker jen dívá */
function takeForm(PairedTabletService $service, array $post): JsonResponse
{
	$presenter = new TestTabletDevicePresenter();
	$presenter->injectPrimary(
		new Request(
			new UrlScript('https://localhost/klient/tablet?do=takeForm'),
			post: $post,
			cookies: [Helpers::StrictCookieName => '1'],
			method: 'POST',
		),
		new TestHttpResponse(),
	);
	$presenter->setTabletService($service);

	return $presenter->run(new AppRequest('TabletDevice', 'POST', ['action' => 'default', 'do' => 'takeForm'], $post));
}


test('dotaz čekací stránky vydá formulář a ohlásí, že stránka svítí', function () {
	$service = new PairedTabletService((new PairedTestTablet())->setName('Recepce'));

	$response = takeForm($service, ['poll' => '1']);

	Assert::same(['ok' => true, 'form' => ['url' => '/klient/formular/1', 'title' => 'Souhlas']], $response->getPayload());
	Assert::same(1, $service->seen);
});


test('vyzvednutí bez poll=1 formulář vydá, čekací stránku tím ale neohlásí', function () {
	$service = new PairedTabletService((new PairedTestTablet())->setName('Recepce'));

	$response = takeForm($service, []);

	Assert::same(['ok' => true, 'form' => ['url' => '/klient/formular/1', 'title' => 'Souhlas']], $response->getPayload());
	Assert::same(0, $service->seen);
	Assert::same(1, $service->taken);
});


test('service worker se po push zprávě na formulář jen dívá - vyzvedne si ho až čekací stránka', function () {
	$service = new PairedTabletService((new PairedTestTablet())->setName('Recepce'));

	$response = takeForm($service, ['peek' => '1']);

	Assert::same(['ok' => true, 'form' => ['url' => '/klient/formular/1', 'title' => 'Souhlas']], $response->getPayload());
	Assert::same(0, $service->taken);
	Assert::same(0, $service->seen);
});
