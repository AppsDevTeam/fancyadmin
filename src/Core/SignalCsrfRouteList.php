<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Core;

use ADT\Routing\RouteList;
use Nette\Application\UI\Presenter;
use Nette\Http\UrlScript;

/**
 * Korenovy seznam rout, ktery hlida, ze CSRF token signalu konci jen v adresach signalu.
 *
 * Token je persistentni parametr presenteru (viz {@see \ADT\FancyAdmin\UI\Presenters\SignalCsrfProtection}),
 * takze by ho Nette pripojilo ke kazde vygenerovane adrese - i k te, kde se nijak neoveruje.
 * Tam nic nechrani a jen by se vlekl adresnim radkem, historii prohlizece, hlavickou Referer
 * a logy serveru.
 *
 * Odstranuje se to az tady, protoze tudy projde kazda adresa, kterou aplikace postavi -
 * odkazy v sablonach, odkazy komponent a gridu, presmerovani i kanonizace. Prezenter sam
 * takove misto nema: Nette\Application\LinkGenerator slozi parametry az po tom, co se ho
 * zepta na stav, a signal do nich prida jeste pozdeji.
 *
 * Projekt ho pouzije jako koren sveho RouterFactory misto obycejneho RouteListu; ze na to
 * nezapomnel hlida {@see \ADT\FancyAdmin\DI\FancyAdminExtension::checkSignalCsrfRouter()}.
 * Router se zamerne neobaluje zvenku: knihovny i Tracy si strom rout prochazeji
 * (`getRouters()`, `createRoute()`) a obal by jim ho schoval o patro niz.
 */
class SignalCsrfRouteList extends RouteList
{
	/** Jmeno parametru je dane nazvem vlastnosti v SignalCsrfProtection, proto natvrdo. */
	private const string TOKEN_PARAM = '_sec';

	public function constructUrl(array $params, UrlScript $refUrl): ?string
	{
		if (!isset($params[Presenter::SignalKey])) {
			unset($params[self::TOKEN_PARAM]);
		}

		return parent::constructUrl($params, $refUrl);
	}
}
