<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use ADT\FancyAdmin\Model\Queries\Factories\TabletQueryFactory;
use ADT\FancyAdmin\Model\WebPush\WebPushSender;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Localization\Translator;
use Tracy\Debugger;

/**
 * Tablety: párování, klíč zařízení a odeslání formuláře přes Web Push (docs/tablets.md).
 *
 * Párovací token (QR v administraci) je jednorázový a platí PAIRING_LIFETIME. Tablet za něj
 * dostane klíč zařízení, který si drží v cookie a kterým se pak prokazuje. V DB jsou jen
 * SHA-256 hashe obou hodnot.
 *
 * Formulář: každé odeslání je žádost o podpis (TabletSignRequest, vlastní tabulka) - podle
 * nevyzvednuté žádosti tablet pozná, že má co otevřít, a zároveň je to evidence, kdo, kdy a co
 * na tablet poslal a kdy se to podepsalo. Odkaz si vyzvedne čekací stránka tabletu
 * (takePendingForm, ověřeno cookie tabletu) - nejde tak přes push službu prohlížeče. Stránka
 * se ptá při načtení, po návratu do popředí a když jí to řekne service worker: toho probudí
 * prázdná push zpráva, ukáže notifikaci (peekPendingForm) a formulář nechá na stránce. Formulář
 * tak dorazí i na tablet bez notifikací nebo když se push ztratí - stačí na něm čekací stránku
 * otevřít nebo obnovit. V rozestupu se stránka sama neptá.
 *
 * Tablet má nejvýš jednu nevyřízenou žádost: nová zruší předchozí. Žádost končí podpisem
 * (finish) nebo zrušením (release) - tablet se vrátil na čekací stránku, byl znovu spárován
 * nebo odpojen. Stránka formuláře má ověřit, že ji otevírá tablet se svou otevřenou žádostí
 * (getCurrentRequest()), ať odkaz nejde otevřít jinde ani později (historie prohlížeče,
 * další zákazník). Co dalšího má projekt při uvolnění tabletu udělat, patří do $onRelease.
 */
class TabletService
{
	public const string PAIRING_LIFETIME = '+15 minutes';

	/** Jak dlouho si tablet může odeslaný formulář vyzvednout (zhasnutý, chvíli offline) */
	public const string PENDING_FORM_LIFETIME = '+10 minutes';

	private const string COOKIE_EXPIRE = '+5 years';

	/** @var array<callable(Tablet): void> tablet je zpátky na čekací stránce, znovu spárovaný nebo odpojený */
	public array $onRelease = [];

	public function __construct(
		private readonly EntityManager $em,
		private readonly TabletQueryFactory $tabletQueryFactory,
		private readonly WebPushSender $webPushSender,
		private readonly Translator $translator,
		private readonly IRequest $httpRequest,
		private readonly IResponse $httpResponse,
		private readonly TabletSignRequests $signRequests,
		/** Cookie s klíčem zařízení */
		private readonly string $cookieName = 'fancyadmin_tablet',
		/** Cesta cookie = rozsah service workeru: musí pokrýt čekací stránku i stránky formulářů */
		private readonly string $cookiePath = '/',
	) {
	}

	public function getCookiePath(): string
	{
		return $this->cookiePath;
	}

	/** Čekací stránka se zeptala na formulář - tablet ji má právě otevřenou (Naposledy připojen) */
	public function markSeen(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): void
	{
		$tablet->setLastSeenAt($now);
		$this->em->flush();
	}

	/** Nový párovací token - vrací ho jen tady (do QR), předchozí token tabletu tím propadne */
	public function createPairingToken(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): string
	{
		$token = self::generateSecret();
		$tablet->setPairingToken(self::hash($token), $now->modify(self::PAIRING_LIFETIME));
		$this->em->flush();

		return $token;
	}

	/**
	 * Spárování tabletu platným párovacím tokenem. Vrací klíč zařízení pro cookie,
	 * null = token neexistuje, už byl použitý nebo propadl.
	 *
	 * @return array{Tablet, string}|null
	 */
	public function pair(#[\SensitiveParameter] string $pairingToken, \DateTimeImmutable $now = new \DateTimeImmutable()): ?array
	{
		/** @var Tablet|null $tablet */
		$tablet = $this->tabletQueryFactory->create()->disableSecurityFilter()
			->byPairingTokenHash(self::hash($pairingToken))
			->fetchOneOrNull();
		if ($tablet === null || $tablet->getPairingExpiresAt() === null || $tablet->getPairingExpiresAt() <= $now) {
			return null;
		}

		$this->release($tablet, $now);
		$deviceSecret = self::generateSecret();
		$tablet->pair(self::hash($deviceSecret), $now)->setLastSeenAt($now);
		$this->em->flush();

		return [$tablet, $deviceSecret];
	}

	/** Spáruje zařízení, ze kterého přišel požadavek (cookie s klíčem), null = neplatný token */
	public function pairCurrentDevice(#[\SensitiveParameter] string $pairingToken): ?Tablet
	{
		$paired = $this->pair($pairingToken);
		if ($paired === null) {
			return null;
		}

		[$tablet, $deviceSecret] = $paired;
		$this->httpResponse->setCookie(
			$this->cookieName,
			$deviceSecret,
			self::COOKIE_EXPIRE,
			$this->cookiePath,
			httpOnly: true,
			sameSite: 'Lax',
		);

		return $tablet;
	}

	public function findByDeviceSecret(#[\SensitiveParameter] ?string $deviceSecret): ?Tablet
	{
		if ($deviceSecret === null || $deviceSecret === '') {
			return null;
		}

		/** @var Tablet|null $tablet */
		$tablet = $this->tabletQueryFactory->create()->disableSecurityFilter()
			->byDeviceSecretHash(self::hash($deviceSecret))
			->fetchOneOrNull();

		return $tablet;
	}

	/** Spárovaný tablet, ze kterého přišel požadavek (cookie s klíčem zařízení) */
	public function getCurrentTablet(): ?Tablet
	{
		return $this->findByDeviceSecret($this->httpRequest->getCookie($this->cookieName));
	}

	/**
	 * Tablet povolil notifikace (nebo prohlížeč odběr obnovil).
	 *
	 * @return bool false = adresa odběru není https na známou push službu
	 */
	public function registerPushEndpoint(Tablet $tablet, string $endpoint, \DateTimeImmutable $now = new \DateTimeImmutable()): bool
	{
		if (!WebPushSender::isValidEndpoint($endpoint)) {
			return false;
		}
		$tablet->setPushEndpoint($endpoint)->setLastSeenAt($now);
		$this->em->flush();

		return true;
	}

	/**
	 * Vyzvednutí čekající žádosti čekací stránkou (ta ji i otevře) - vydá se jen jednou a jen
	 * do vypršení. Od vyzvednutí je žádost otevřená (getOpenRequest()).
	 */
	public function takePendingRequest(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): ?TabletSignRequest
	{
		$request = $this->signRequests->findPending($tablet, $now);
		if ($request === null) {
			return null;
		}

		$request->markOpened($now);
		$this->em->flush();

		return $request;
	}

	/**
	 * Vyzvednutí čekajícího formuláře pro čekací stránku tabletu (takeForm!).
	 *
	 * @return array{url: string, title: string}|null
	 */
	public function takePendingForm(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): ?array
	{
		return self::toForm($this->takePendingRequest($tablet, $now));
	}

	/**
	 * Čekající formulář bez vyzvednutí - service worker podle něj po push zprávě napíše
	 * notifikaci, formulář ale dál čeká na čekací stránku. Worker ho sám otevřít nemusí umět
	 * (iOS po klepnutí na notifikaci otevře aplikaci na čekací stránce, ne na odkazu z workeru),
	 * a kdyby si ho vyzvedl, stránka by už nedostala nic.
	 *
	 * @return array{url: string, title: string}|null
	 */
	public function peekPendingForm(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): ?array
	{
		return self::toForm($this->signRequests->findPending($tablet, $now));
	}

	/** Žádost, kterou tablet právě zobrazuje (vyzvednutá, nepodepsaná, nezrušená) */
	public function getOpenRequest(Tablet $tablet): ?TabletSignRequest
	{
		return $this->signRequests->findOpen($tablet);
	}

	/**
	 * Otevřená žádost tabletu, ze kterého přišel požadavek (cookie zařízení) - pro stránku
	 * formuláře: bez ní (jiné zařízení, tablet už je volný) formulář nemá jít otevřít.
	 */
	public function getCurrentRequest(): ?TabletSignRequest
	{
		$tablet = $this->getCurrentTablet();

		return $tablet === null ? null : $this->getOpenRequest($tablet);
	}

	/** Podepsáno (formulář odeslán) - žádost je vyřízená */
	public function finish(TabletSignRequest $request, \DateTimeImmutable $now = new \DateTimeImmutable()): void
	{
		$request->markSigned($now);
		$this->em->flush();
	}

	/**
	 * Tablet otevřel čekací stránku, takže je volný - pokud na něj už nečeká další žádost
	 * (stránka se načetla mezi odesláním a vyzvednutím, třeba po probuzení tabletu).
	 */
	public function returnToWaiting(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): void
	{
		$tablet->setLastSeenAt($now);
		$this->em->flush();

		if ($this->signRequests->findPending($tablet, $now) === null) {
			$this->release($tablet, $now);
		}
	}

	/**
	 * Tablet je volný: jeho nevyřízené žádosti (čekající i otevřená) se ruší, takže to, co mu
	 * bylo posláno, už nejde otevřít. Co dalšího má projekt udělat, patří do $onRelease.
	 */
	public function release(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): void
	{
		$this->cancelUnfinished($tablet, $now);
		$this->em->flush();

		foreach ($this->onRelease as $callback) {
			$callback($tablet);
		}
	}

	public function disconnect(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): void
	{
		$this->release($tablet, $now);
		$tablet->disconnect();
		$this->em->flush();
	}

	/**
	 * Odeslání odkazu na formulář do tabletu: založí žádost o podpis a tablet probudí push
	 * zprávou. Hláška ve výsledku říká obsluze, co dál, null = odesláno (tablet ukáže notifikaci
	 * a otevřená čekací stránka formulář rovnou otevře).
	 *
	 * Žádost čeká na spárovaný tablet (PENDING_FORM_LIFETIME) i tehdy, když push neodešel -
	 * tablet nemá povolené notifikace, odběr už neplatí, push služba neodpovídá, chybí klíč
	 * VAPID. Čekací stránka si ji vyzvedne při načtení, takže ji na tabletu stačí otevřít nebo
	 * obnovit; to obsluze říká hláška. Neplatný odběr se z tabletu smaže. Předchozí nevyřízené
	 * žádosti tabletu se ruší.
	 *
	 * @param (callable(TabletSignRequest): void)|null $configure doplnění žádosti před uložením:
	 *        co se podepisuje (setSubject) a kdo ji poslal (setRequester)
	 */
	public function send(Tablet $tablet, string $url, string $title, ?callable $configure = null, \DateTimeImmutable $now = new \DateTimeImmutable()): TabletSendResult
	{
		$name = ['name' => $tablet->getName()];
		if (!$tablet->isPaired()) {
			return new TabletSendResult(null, $this->translator->translate('fcadmin.tablets.messages.notPaired', $name));
		}

		// Odkaz čeká u nás, push jen probudí tablet
		$this->cancelUnfinished($tablet, $now);
		$request = $this->signRequests->create($tablet, $url, $title, $now, $now->modify(self::PENDING_FORM_LIFETIME));
		if ($configure !== null) {
			$configure($request);
		}
		$this->signRequests->add($request);
		$this->em->flush();

		$endpoint = $tablet->getPushEndpoint();
		if ($endpoint !== null && $endpoint !== '') {
			if (!$this->webPushSender->isConfigured()) {
				Debugger::log(sprintf('Chybí klíč VAPID (fancyadmin: webPush: vapidPrivateKey), formulář %s do tabletu %s neodešel přes push.', $title, $tablet->getName()), 'webpush');
			} else {
				try {
					if ($this->webPushSender->send($endpoint)) {
						return new TabletSendResult($request, null);
					}

					// Odběr už neplatí (tablet odvolal notifikace)
					$tablet->setPushEndpoint(null);
					$this->em->flush();
				} catch (\Throwable $e) {
					Debugger::log($e, 'webpush');
				}
			}
		}

		return new TabletSendResult($request, $this->translator->translate('fcadmin.tablets.messages.notNotified', $name));
	}

	/** Odeslání formuláře bez dalších údajů žádosti - vrací jen hlášku pro obsluhu, viz send() */
	public function sendForm(Tablet $tablet, string $url, string $formName, \DateTimeImmutable $now = new \DateTimeImmutable()): ?string
	{
		return $this->send($tablet, $url, $formName, null, $now)->message;
	}

	private function cancelUnfinished(Tablet $tablet, \DateTimeImmutable $now): void
	{
		foreach ($this->signRequests->findUnfinished($tablet, $now) as $request) {
			$request->cancel($now);
		}
	}

	/** @return array{url: string, title: string}|null */
	private static function toForm(?TabletSignRequest $request): ?array
	{
		return $request === null ? null : ['url' => $request->getUrl(), 'title' => $request->getTitle()];
	}

	/** 256 bitů z CSPRNG v base64url (do URL i cookie bez escapování) */
	public static function generateSecret(): string
	{
		return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
	}

	public static function hash(#[\SensitiveParameter] string $value): string
	{
		return hash('sha256', $value);
	}
}
