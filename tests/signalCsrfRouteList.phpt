<?php

declare(strict_types=1);

use ADT\FancyAdmin\Core\SignalCsrfRouteList;
use Nette\Http\UrlScript;
use Tester\Assert;

/**
 * CSRF token signalu patri jen do adresy signalu.
 *
 * Token je persistentni parametr presenteru, takze by ho Nette pripojilo ke kazde
 * vygenerovane adrese. Tam, kde se neoveruje, nic nechrani a jen by se vlekl adresnim
 * radkem, historii prohlizece, hlavickou Referer a logy serveru.
 */

require __DIR__ . '/bootstrap.php';


function createRouter(): SignalCsrfRouteList
{
	$router = new SignalCsrfRouteList();
	$router->addRoute('<presenter>/<action>', ['presenter' => 'Homepage', 'action' => 'default']);

	return $router;
}


test('bezna adresa token neodnese', function () {
	$url = createRouter()->constructUrl(
		['presenter' => 'Devices', 'action' => 'default', '_sec' => 'tajnytoken'],
		new UrlScript('https://admin.example.com/'),
	);

	Assert::notContains('_sec', (string) $url);
});


test('adresa signalu token nese', function () {
	$url = createRouter()->constructUrl(
		['presenter' => 'Devices', 'action' => 'default', 'do' => 'removeAllFirebaseTokens', '_sec' => 'tajnytoken'],
		new UrlScript('https://admin.example.com/'),
	);

	Assert::contains('_sec=tajnytoken', (string) $url);
	Assert::contains('do=removeAllFirebaseTokens', (string) $url);
});


test('ostatni parametry zustavaji nedotcene', function () {
	$url = createRouter()->constructUrl(
		['presenter' => 'Devices', 'action' => 'default', 'grid-page' => 2, '_sec' => 'tajnytoken'],
		new UrlScript('https://admin.example.com/'),
	);

	Assert::contains('grid-page=2', (string) $url);
	Assert::notContains('_sec', (string) $url);
});
