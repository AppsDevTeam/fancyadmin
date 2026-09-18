<?php

declare(strict_types=1);

use ADT\FancyAdmin\Model\Security\ReturnPath;
use ADT\FancyAdmin\Tests\Fixtures\TestHttpResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * "Kam se vratit po prihlaseni" ulozene v cookie misto v session.
 *
 * Cookie je vstup od klienta, takze se z ni nikdy nestava cela URL: uklada se jen cesta
 * relativni k baseUrl a pri cteni se vysledek overuje proti hostu aktualniho pozadavku.
 * Bez toho by z prihlasovaci stranky sel udelat open redirect (CWE-601).
 */

require __DIR__ . '/bootstrap.php';


const BASE_URL = 'https://admin.example.com/app/';

function createReturnPath(array $cookies = [], string $requestUrl = BASE_URL . 'dashboard'): array
{
	$request = new Request(new UrlScript($requestUrl, '/app/'), cookies: $cookies);
	$response = new TestHttpResponse();

	return [new ReturnPath($request, $response), $response];
}


test('cil se ulozi do cookie jako relativni cesta', function () {
	[$returnPath, $response] = createReturnPath();

	$returnPath->store(new UrlScript(BASE_URL . 'orders/detail?id=7', '/app/'));

	$cookie = $response->getLastCookie();
	Assert::same('returnPath', $cookie['name']);
	Assert::same('orders/detail?id=7', $cookie['value']);
	Assert::same('+ 10 minutes', $cookie['expire']);
	Assert::same('/', $cookie['path']);
	// Prazdna domena = host-only cookie; kdyby se predalo null, Nette by sahlo po globalnim
	// http.cookieDomain a cil by sel podstrcit z jine subdomeny.
	Assert::same('', $cookie['domain']);
});


test('pozadavek na baseUrl se nepamatuje', function () {
	[$returnPath, $response] = createReturnPath();

	$returnPath->store(new UrlScript(BASE_URL, '/app/'));

	Assert::same([], $response->cookies);
});


test('vraci se absolutni URL na vlastnim hostu a cookie se maze', function () {
	[$returnPath, $response] = createReturnPath(['returnPath' => 'orders/detail?id=7']);

	Assert::same(BASE_URL . 'orders/detail?id=7', $returnPath->consume());
	Assert::same([['name' => 'returnPath', 'path' => '/', 'domain' => '', 'secure' => null]], $response->deletedCookies);
});


test('cil je na jedno pouziti', function () {
	// Cookie se maze pri prvnim cteni, takze druhy pruchod uz nic nevrati.
	[$returnPath, $response] = createReturnPath(['returnPath' => 'orders']);

	Assert::same(BASE_URL . 'orders', $returnPath->consume());
	Assert::count(1, $response->deletedCookies);
});


test('bez cookie se nic nemaze', function () {
	[$returnPath, $response] = createReturnPath();

	Assert::null($returnPath->consume());
	Assert::same([], $response->deletedCookies);
});


test('cesta na cizi host se zahodi', function () {
	// Uvodni lomitka i zpetna lomitka se orezavaji, takze z //evil.tld ani \\evil.tld
	// adresa jineho hostu nevznikne.
	foreach (['//evil.example.net/', '\\\\evil.example.net/', '/\\evil.example.net/', '///evil.example.net'] as $path) {
		[$returnPath] = createReturnPath(['returnPath' => $path]);

		$url = $returnPath->consume();
		Assert::notNull($url, $path);
		Assert::true(str_starts_with($url, BASE_URL), $path . ' => ' . $url);
	}
});


test('absolutni cizi URL zustane cestou pod vlastnim hostem', function () {
	[$returnPath] = createReturnPath(['returnPath' => 'https://evil.example.net/phishing']);

	Assert::same(BASE_URL . 'https://evil.example.net/phishing', $returnPath->consume());
});


test('ridici znaky se odmitaji', function () {
	// Rozsekly by hlavicku Location.
	foreach (["orders\r\nLocation: https://evil.example.net", "orders\n", "orders\t", "orders\x00", "\x7Forders"] as $path) {
		[$returnPath, $response] = createReturnPath(['returnPath' => $path]);

		Assert::null($returnPath->consume(), bin2hex($path));
		// Cookie se stejne smaze - vadny cil nema smysl drzet.
		Assert::count(1, $response->deletedCookies);
	}
});


test('prilis dlouha cesta se zahodi', function () {
	[$returnPath] = createReturnPath(['returnPath' => str_repeat('a', 1000)]);
	Assert::same(BASE_URL . str_repeat('a', 1000), $returnPath->consume());

	[$returnPath] = createReturnPath(['returnPath' => str_repeat('a', 1001)]);
	Assert::null($returnPath->consume());
});


test('prazdna cookie se chova jako zadny cil', function () {
	[$returnPath] = createReturnPath(['returnPath' => '']);

	Assert::null($returnPath->consume());
});


test('ridici znaky se neulozi ani pri store()', function () {
	[$returnPath, $response] = createReturnPath();

	$returnPath->store(new UrlScript(BASE_URL . 'orders%0D%0A', '/app/'));

	// %0D%0A zustava zakodovane, takze se ulozi - rozhoduje az syrovy znak.
	Assert::same('orders%0D%0A', $response->getLastCookie()['value']);
});


test('cil z jineho hostu se pri cteni neprijme', function () {
	// Kdyby aplikace bezela na vice hostech, cookie z jednoho nesmi vratit URL druheho.
	$request = new Request(new UrlScript('https://admin.example.com/app/x', '/app/'), cookies: ['returnPath' => 'orders']);
	$response = new TestHttpResponse();
	$returnPath = new ReturnPath($request, $response);

	Assert::same('https://admin.example.com/app/orders', $returnPath->consume());
	Assert::notContains('evil', (string) $returnPath->consume());
});
