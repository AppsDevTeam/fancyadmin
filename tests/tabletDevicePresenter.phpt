<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Entities\Tablet;
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
