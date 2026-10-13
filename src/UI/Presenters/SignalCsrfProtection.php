<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters;

use ADT\FancyAdmin\Core\SignalCsrfRouteList;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;
use Nette\Utils\Random;

/**
 * CSRF token pro signaly presenteru (nalez WEB-06 z pentestu AURA).
 *
 * Stavove akce administrace se spousti signaly - odkazy a formulare typu `?do=...`. Jedinou
 * ochranou pred CSRF u nich byl atribut SameSite u cookie a hlavicka Sec-Fetch-Site
 * (Nette ji kontroluje u metod handle*), tedy obrana zalozena na chovani prohlizece.
 * Nalez stal na `POST /sso?do=new`, ktere bez tokenu probehlo.
 *
 * Token je persistentni parametr presenteru, takze ho Nette umi pripojit ke kazde adrese,
 * kterou administrace vygeneruje - k odkazum na signaly v sablonach i v komponentach
 * (gridy). Diky tomu plati pro vsechny handle* metody naraz a neni potreba na zadnou
 * z nich sahat.
 *
 * Do adresy se ale dostane jen tam, kde ho {@see processSignal()} opravdu overuje, tedy
 * k signalu; z ostatnich ho odklizi {@see SignalCsrfRouteList}. Jinde by nic nechranil
 * a jen by se vlekl adresnim radkem, historii prohlizece, hlavickou Referer a logy
 * serveru - a uzivatel by ho kopiroval do ticketu spolu s adresou stranky.
 *
 * Formulare proto token nedostanou do adresy, kam odesilaji, ale do tela pozadavku:
 * posila se do prohlizece cookie `_sec` a skript `assets/js/signalCsrf.js` ho pred
 * odeslanim doplni do formulare. Formular s vlastni ochranou (`addProtection()`, viz
 * {@see \ADT\FancyAdmin\UI\Components\Forms\BaseFormTrait}) ji nepotrebuje a je
 * z kontroly vyjmuty, takze na JavaScriptu nezavisi - viz {@see isSignalCsrfExempt()}.
 *
 * Cookie smi cist skript, protoze presne to je jeji ucel. Sezeni nechrani - to zustava
 * v HttpOnly cookie - a token z ni cizi web neprecte (same-origin policy), takze utocnikovi
 * je k nicemu. Overuje se vzdy proti hodnote v relaci, nikdy proti cookie.
 *
 * Plati jen pro prihlaseneho uzivatele. Neprihlaseny nema jmenem ceho jednat, takze neni
 * co podvrhnout, a token by se mu nedal dat do odkazu jinak nez zalozenim session - tedy
 * kazdemu navstevnikovi prihlasovaci stranky jeden radek v tabulce sessions. Presne tomu
 * se v ADT\FancyAdmin\Model\Security\ReturnPath vyhyba i navrat po prihlaseni. Signaly
 * neprihlaseneho dal hlida Nette pres Sec-Fetch-Site.
 *
 * Verejne casti projektu a API to nededi: API se autorizuje hlavickou, ne cookie, takze
 * CSRF na nej nesedi.
 */
trait SignalCsrfProtection
{
	private const string SESSION_SECTION = 'signalCsrf';

	/** Kratke jmeno, protoze se objevuje v adresach signalu. */
	private const string PARAM_NAME = '_sec';

	#[Persistent]
	public ?string $_sec = null;

	private ?string $signalCsrfTokenFromRequest = null;

	/**
	 * @param array<string, mixed> $params
	 */
	public function loadState(array $params): void
	{
		parent::loadState($params);

		// Hodnota z pozadavku slouzi jen k overeni; do odkazu se vzdy vraci token z relace,
		// jinak by podvrzeny `_sec` v adrese rozbil vsechny odkazy na strance.
		$this->signalCsrfTokenFromRequest = $this->_sec ?? $this->getSignalCsrfTokenFromPost();
		$this->_sec = $this->isSignalCsrfProtected() ? $this->getSignalCsrfToken() : null;

		$this->sendSignalCsrfCookie($this->_sec);
	}

	public function processSignal(): void
	{
		if (
			$this->isSignalCsrfProtected()
			&& $this->getSignal() !== null
			&& !$this->isSignalCsrfExempt()
			&& !$this->isSignalCsrfTokenValid()
		) {
			$this->error('Neplatny CSRF token.', IResponse::S403_Forbidden);
		}

		parent::processSignal();
	}

	/**
	 * Kanonizace porovnava aktualni adresu s adresou, kterou by presenter vygeneroval.
	 *
	 * U signalu se do porovnani bere token z pozadavku - jinak by se adresa se spatnym
	 * tokenem presmerovala na spravnou misto toho, aby skoncila na 403. U bezne stranky
	 * se naopak nebere zadny, takze zbyly token (ze zalozky nebo z adresy poslane kolegovi)
	 * kanonizace odklidi presmerovanim.
	 *
	 * Hodnota se predava argumentem, protoze ten ma pri sestavovani adresy prednost pred
	 * stavem presenteru i pred parametry pozadavku.
	 */
	public function canonicalize(?string $destination = null, ...$args): void
	{
		$args = count($args) === 1 && is_array($args[0] ?? null) ? $args[0] : $args;
		$args[self::PARAM_NAME] = $this->getSignal() !== null ? $this->signalCsrfTokenFromRequest : null;

		parent::canonicalize($destination, $args);
	}

	/** Prihlaseni se cte z cookie, takze se tim session nezaklada. */
	private function isSignalCsrfProtected(): bool
	{
		return $this->getUser()->isLoggedIn();
	}

	/**
	 * Formular, ktery si nese vlastni CSRF token (`addProtection()`), uz chraneny je -
	 * token odesila v tele pozadavku a Nette ho overuje pri validaci. Druhy token by mu
	 * nic nepridal a navic by takovy formular zbytecne zavisel na tom, ze se v prohlizeci
	 * provedl skript, ktery `_sec` doplnuje.
	 *
	 * Vyjimka se tyka jen formularu s ochranou. Formular bez ni (filtry a hromadne akce
	 * gridu) dal spoleha na `_sec`.
	 */
	private function isSignalCsrfExempt(): bool
	{
		[$receiverName] = $this->getSignal();
		$receiver = $receiverName === '' ? $this : $this->getComponent($receiverName, throw: false);

		return $receiver instanceof Form
			&& $receiver->getComponent(Form::ProtectorId, throw: false) !== null;
	}

	/** Token je vazany na relaci, takze ho utocnik z ciziho webu neprecte. */
	private function getSignalCsrfToken(): string
	{
		$section = $this->getSession(self::SESSION_SECTION);

		if (!$section->get('token')) {
			$section->set('token', Random::generate(32));
		}

		return (string) $section->get('token');
	}

	private function isSignalCsrfTokenValid(): bool
	{
		return $this->signalCsrfTokenFromRequest !== null
			&& hash_equals($this->getSignalCsrfToken(), $this->signalCsrfTokenFromRequest);
	}

	/** Takhle token posilaji formulare - v tele pozadavku, ne v adrese. */
	private function getSignalCsrfTokenFromPost(): ?string
	{
		$value = $this->getHttpRequest()->getPost(self::PARAM_NAME);

		return is_string($value) ? $value : null;
	}

	/**
	 * Cookie se posila jen pri zmene, aby ji nenesla kazda odpoved. Neni HttpOnly zamerne -
	 * cte ji skript, ktery token doplnuje do formularu.
	 */
	private function sendSignalCsrfCookie(?string $token): void
	{
		$current = $this->getHttpRequest()->getCookie(self::PARAM_NAME);

		if ($token === null) {
			if ($current !== null) {
				$this->getHttpResponse()->deleteCookie(self::PARAM_NAME);
			}

			return;
		}

		if ($current === $token) {
			return;
		}

		$this->getHttpResponse()->setCookie(
			self::PARAM_NAME,
			$token,
			expire: 0,
			httpOnly: false,
			sameSite: IResponse::SameSiteLax,
		);
	}
}
