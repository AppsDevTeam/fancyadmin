<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\TabletDevice;

use ADT\FancyAdmin\DI\Injects\TabletServiceInject;
use ADT\FancyAdmin\DI\Injects\WebPushSenderInject;
use ADT\FancyAdmin\Model\Entities\Tablet;
use Nette\Application\Responses\TextResponse;
use Nette\Http\IResponse;

/**
 * Čekací stránka tabletu (veřejná, bez přihlášení), docs/tablets.md. Tablet se jednou
 * spáruje odkazem z QR kódu (pair), dostane klíč zařízení do cookie a povolí notifikace
 * (Web Push). Formulář mu pak pošle obsluha: čeká u serveru a čekací stránka si ho vyzvedne
 * (takeForm!) a otevře - při načtení, po návratu do popředí a když jí to řekne service worker
 * (sw), kterého probudí push a který k tomu ukáže notifikaci. Na tabletu, který drží zákazník,
 * tak není přihlášený žádný účet.
 *
 * Projekt: presenter s vlastním layoutem (blok content, define title) a routou v rozsahu
 * cookie tabletu (fancyadmin: tablet: cookiePath), na stránce JS `@fancyadmin/js/tablet`.
 * Šablony jde přebít vlastními (default.latte, pair.latte u presenteru projektu).
 */
trait TabletDevicePresenterTrait
{
	use TabletServiceInject;
	use WebPushSenderInject;

	public function actionDefault(): void
	{
		// Signály čekací stránky (registerPush!, takeForm!) jdou na její adresu
		// a Nette pouští akci před signálem. Dotaz na formulář ale není otevření čekací stránky:
		// returnToWaiting() by tablet znovu uvolnil ($onRelease) a projekt by zneplatnil odkaz,
		// který si mezitím vyzvedl service worker po push zprávě.
		if ($this->getSignal() !== null) {
			return;
		}

		$tablet = $this->_tabletService->getCurrentTablet();
		if ($tablet !== null) {
			$this->_tabletService->returnToWaiting($tablet);
		}

		$this->template->tablet = $tablet;
		// Bez klíče VAPID (testy) se tablet k odběru nepřihlásí a ukáže chybu
		$this->template->vapidPublicKey = $this->_webPushSender->isConfigured() ? $this->_webPushSender->getPublicKey() : null;
		$this->template->swScope = $this->_tabletService->getCookiePath();
		$this->setTabletDeviceTemplateFile(__DIR__ . '/default.latte');
	}

	/** Párovací odkaz z QR kódu - jednorázový, platí TabletService::PAIRING_LIFETIME */
	public function actionPair(string $token): void
	{
		if ($this->_tabletService->pairCurrentDevice($token) !== null) {
			$this->redirect('default');
		}

		// Šablona vysvětlí, že odkaz propadl
		$this->setTabletDeviceTemplateFile(__DIR__ . '/pair.latte');
	}

	/**
	 * Service worker tabletu - po push zprávě ukáže notifikaci a čekací stránce řekne, ať si
	 * formulář vyzvedne. Rozsah (cesta cookie) mu povoluje hlavička Service-Worker-Allowed,
	 * aby ovládal i stránky formulářů, na které tablet přejde.
	 */
	public function actionSw(): void
	{
		// Worker volá signál na téhle adrese ({link takeForm!} = sw?do=takeForm) a Nette pouští
		// akci před signálem - odpověď odsud by signál přeskočila a worker by místo formuláře
		// dostal sám sebe (JSON se nepřečte, notifikace by formulář nepojmenovala)
		if ($this->getSignal() !== null) {
			return;
		}

		/** @var \Nette\Bridges\ApplicationLatte\Template $template */
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/sw.js.latte');

		$response = $this->getHttpResponse();
		$response->setContentType('application/javascript', 'utf-8');
		$response->setHeader('Service-Worker-Allowed', $this->_tabletService->getCookiePath());
		$response->setHeader('Cache-Control', 'no-cache');
		// Service worker se řídí CSP vlastní odpovědi - žádné cizí skripty, jen dotaz na náš server
		$response->setHeader('Content-Security-Policy', "default-src 'none'; connect-src 'self'");

		$this->sendResponse(new TextResponse($template->renderToString()));
	}

	/** Tablet povolil notifikace nebo prohlížeč obnovil odběr Web Push (POST z čekací stránky) */
	public function handleRegisterPush(): void
	{
		$tablet = $this->requirePostFromTablet();
		$endpoint = $this->getHttpRequest()->getPost('endpoint');
		if (!is_string($endpoint) || !$this->_tabletService->registerPushEndpoint($tablet, $endpoint)) {
			$this->getHttpResponse()->setCode(IResponse::S400_BadRequest);
			$this->sendJson(['ok' => false]);
		}

		$this->sendJson(['ok' => true]);
	}

	/**
	 * Odeslaný formulář, null = nic nečeká. Čekací stránka si ho vyzvedává (jen jednou) a tím
	 * zároveň hlásí, že je otevřená (POST poll=1). Service worker se po push zprávě jen dívá
	 * (POST peek=1), aby věděl, co napsat do notifikace - formulář dál čeká na stránku.
	 */
	public function handleTakeForm(): void
	{
		$tablet = $this->requirePostFromTablet();
		$post = $this->getHttpRequest();
		if ($post->getPost('poll') !== null) {
			$this->_tabletService->markSeen($tablet);
		}

		$this->sendJson([
			'ok' => true,
			'form' => $post->getPost('peek') !== null
				? $this->_tabletService->peekPendingForm($tablet)
				: $this->_tabletService->takePendingForm($tablet),
		]);
	}

	private function requirePostFromTablet(): Tablet
	{
		$tablet = $this->_tabletService->getCurrentTablet();
		if (!$this->getHttpRequest()->isMethod('POST') || $tablet === null) {
			$this->getHttpResponse()->setCode(IResponse::S400_BadRequest);
			$this->sendJson(['ok' => false]);
		}

		return $tablet;
	}

	/** Šablona fancyadminu, pokud ji projekt nemá u svého presenteru */
	private function setTabletDeviceTemplateFile(string $file): void
	{
		$template = $this->getTemplate();
		foreach ($this->formatTemplateFiles() as $_file) {
			if (is_file($_file)) {
				return;
			}
		}

		$template->setFile($file);
	}
}
