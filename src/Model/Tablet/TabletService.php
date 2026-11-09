<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Tablet;

use ADT\DoctrineComponents\EntityManager;
use ADT\FancyAdmin\Model\Entities\Tablet;
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
 * Formulář: odkaz se uloží k tabletu jako čekající (PENDING_FORM_LIFETIME) a push zpráva
 * (prázdná) probudí service worker tabletu. Ten si odkaz vyzvedne (takePendingForm, ověřeno
 * cookie tabletu) a otevře ho na čekací stránce, nebo ukáže notifikaci. Odkaz tak nejde
 * přes push službu prohlížeče.
 *
 * Odkaz na formulář patří projektu. Ten ho má svázat s tabletem (getCurrentTablet() na
 * stránce formuláře) a zneplatnit ho v $onRelease - tablet se vrátil na čekací stránku
 * nebo byl odpojený, takže to, co mu bylo posláno, už nemá jít otevřít (historie prohlížeče,
 * další zákazník).
 */
class TabletService
{
	public const string PAIRING_LIFETIME = '+15 minutes';

	/** Jak dlouho si tablet může odeslaný formulář vyzvednout (zhasnutý, chvíli offline) */
	public const string PENDING_FORM_LIFETIME = '+10 minutes';

	private const string COOKIE_EXPIRE = '+5 years';

	/** @var array<callable(Tablet): void> tablet je zpátky na čekací stránce nebo odpojený */
	public array $onRelease = [];

	public function __construct(
		private readonly EntityManager $em,
		private readonly TabletQueryFactory $tabletQueryFactory,
		private readonly WebPushSender $webPushSender,
		private readonly Translator $translator,
		private readonly IRequest $httpRequest,
		private readonly IResponse $httpResponse,
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

		$this->release($tablet);
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
	 * Čekající formulář pro service worker tabletu - vydá se jen jednou a jen do vypršení.
	 *
	 * @return array{url: string, title: string}|null
	 */
	public function takePendingForm(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): ?array
	{
		$url = $tablet->getPendingFormUrl();
		$title = (string) $tablet->getPendingFormTitle();
		$expiresAt = $tablet->getPendingFormExpiresAt();
		if ($url === null && $expiresAt === null) {
			return null;
		}

		$tablet->setPendingForm(null, null, null);
		$this->em->flush();

		return $url !== null && $expiresAt !== null && $expiresAt > $now ? ['url' => $url, 'title' => $title] : null;
	}

	/**
	 * Tablet otevřel čekací stránku, takže je volný - pokud na něj už nečeká další formulář
	 * (stránka se načetla mezi odesláním a vyzvednutím, třeba po probuzení tabletu).
	 */
	public function returnToWaiting(Tablet $tablet, \DateTimeImmutable $now = new \DateTimeImmutable()): void
	{
		$tablet->setLastSeenAt($now);
		$this->em->flush();

		$pendingUntil = $tablet->getPendingFormExpiresAt();
		if ($pendingUntil === null || $pendingUntil <= $now) {
			$this->release($tablet);
		}
	}

	/** Projekt v $onRelease zneplatní odkazy, které tabletu poslal */
	public function release(Tablet $tablet): void
	{
		foreach ($this->onRelease as $callback) {
			$callback($tablet);
		}
	}

	public function disconnect(Tablet $tablet): void
	{
		$this->release($tablet);
		$tablet->disconnect();
		$this->em->flush();
	}

	/**
	 * Odeslání odkazu na formulář do tabletu. Vrací chybu pro obsluhu, null = odesláno.
	 * Neplatný odběr (tablet odvolal notifikace) se z tabletu smaže.
	 */
	public function sendForm(Tablet $tablet, string $url, string $formName, \DateTimeImmutable $now = new \DateTimeImmutable()): ?string
	{
		$endpoint = $tablet->getPushEndpoint();
		if (!$tablet->isPaired() || $endpoint === null || $endpoint === '') {
			return $this->translator->translate('fcadmin.tablets.messages.notificationsDisabled', ['name' => $tablet->getName()]);
		}
		if (!$this->webPushSender->isConfigured()) {
			Debugger::log(sprintf('Chybí klíč VAPID (fancyadmin: webPush: vapidPrivateKey), formulář %s do tabletu %s neodešel.', $formName, $tablet->getName()), 'webpush');
			return $this->translator->translate('fcadmin.tablets.messages.pushNotConfigured');
		}

		// Odkaz čeká u nás, push jen probudí tablet, aby si ho vyzvedl
		$tablet->setPendingForm($url, mb_substr($formName, 0, 255), $now->modify(self::PENDING_FORM_LIFETIME));
		$this->em->flush();

		try {
			if (!$this->webPushSender->send($endpoint)) {
				$tablet->setPushEndpoint(null)->setPendingForm(null, null, null);
				$this->em->flush();
				return $this->translator->translate('fcadmin.tablets.messages.subscriptionExpired', ['name' => $tablet->getName()]);
			}
		} catch (\Throwable $e) {
			Debugger::log($e, 'webpush');
			return $this->translator->translate('fcadmin.tablets.messages.sendFailed');
		}

		return null;
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
