<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Security;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Url;
use Nette\Http\UrlScript;
use Throwable;

/**
 * "Kam se vrátit po přihlášení" uložené v cookie místo v session.
 *
 * Nette backlink (Presenter::storeRequest) ukládá celý Request do session, takže
 * i pouhé kliknutí nepřihlášeného uživatele na odkaz z e-mailu založí session
 * záznam - a kdokoli zvenčí tím umí nafouknout tabulku se sessionami. Zapamatovat
 * si cílovou cestu ale stačí a ta se vejde do cookie, takže session vzniká až
 * přihlášenému uživateli; viz AuthPresenterTrait::startup().
 *
 * Ukládá se takhle každý nepřihlášený požadavek bez ohledu na metodu. Rozlišovat
 * GET a POST nemá smysl: na obejití by stačil GET s hlavičkou
 * X-Requested-With: XMLHttpRequest. Cenou je, že se POST po přihlášení nezopakuje.
 *
 * Cookie je vstup od klienta (HttpOnly brání JS, ne podvrženému requestu), proto
 * se z ní nikdy nestává celá URL: ukládá se jen cesta relativní k baseUrl a při
 * čtení se výsledek ověřuje proti hostu aktuálního požadavku. Bez toho by
 * z přihlašovací stránky šel udělat open redirect na phishingovou adresu.
 */
final readonly class ReturnPath
{
	private const string COOKIE_NAME = 'returnPath';

	/** Stejná životnost jako u session backlinku (Presenter::storeRequest). */
	private const string EXPIRATION = '+ 10 minutes';

	/** Pojistka proti nafouknuté cookie - delší cíl zahodíme a uživatel skončí na výchozí stránce. */
	private const int MAX_LENGTH = 1000;

	/**
	 * Prázdná doména = host-only cookie, tzn. cíl se vrátí jen na hostu, kde vznikl, a nedá
	 * se podstrčit na jinou (např. zákaznickou) subdoménu. Předává se explicitně, protože
	 * Nette by u $domain = null sáhlo po globálním http.cookieDomain - a to si aplikace může
	 * nastavit na sdílenou doménu, čímž by tahle záruka tiše zmizela.
	 */
	private const string COOKIE_DOMAIN = '';

	/** Musí sedět v store() i consume(), jinak by se mazala jiná cookie, než která vznikla. */
	private const string COOKIE_PATH = '/';

	public function __construct(
		private IRequest $httpRequest,
		private IResponse $httpResponse,
	) {
	}

	/** Zapamatuje si cíl, na který se uživatel po přihlášení vrátí. */
	public function store(UrlScript $url): void
	{
		if (($path = $url->getRelativeUrl()) === '' || $this->toLocalUrl($path) === null) {
			return;
		}

		// httpOnly a SameSite=Lax jsou v Nette default, secure se řídí http.cookieSecure.
		// Lax stačí, protože jde o top-level GET navigaci (klik z e-mailu).
		$this->httpResponse->setCookie(
			self::COOKIE_NAME,
			$path,
			self::EXPIRATION,
			self::COOKIE_PATH,
			self::COOKIE_DOMAIN,
		);
	}

	/**
	 * Vrátí absolutní URL cíle, nebo null, pokud si není kam vracet.
	 * Cookie se maže vždy - cíl je na jedno použití.
	 */
	public function consume(): ?string
	{
		if (($path = $this->httpRequest->getCookie(self::COOKIE_NAME)) === null) {
			return null;
		}

		$this->httpResponse->deleteCookie(self::COOKIE_NAME, self::COOKIE_PATH, self::COOKIE_DOMAIN);

		return $this->toLocalUrl($path);
	}

	/**
	 * Složí absolutní URL v rámci aplikace, nebo null, pokud cesta z cookie neprojde.
	 * Řídicí znaky by mohly rozsekat hlavičku Location, úvodní lomítka a zpětná
	 * lomítka udělat z cesty adresu jiného hostu (//evil.tld), takže padají oboje;
	 * kontrola hostu na konci je pojistka pro vše ostatní.
	 */
	private function toLocalUrl(string $path): ?string
	{
		if ($path === '' || strlen($path) > self::MAX_LENGTH || preg_match('#[\x00-\x1F\x7F]#', $path)) {
			return null;
		}

		$requestUrl = $this->httpRequest->getUrl();

		try {
			$url = new Url($requestUrl->getBaseUrl() . ltrim($path, '/\\'));
		} catch (Throwable) {
			return null;
		}

		return $url->getHostUrl() === $requestUrl->getHostUrl()
			? (string) $url
			: null;
	}
}
