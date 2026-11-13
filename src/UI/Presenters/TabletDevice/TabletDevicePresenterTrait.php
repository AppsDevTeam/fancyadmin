<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\TabletDevice;

use ADT\BiometricSignature\SignatureCapture;
use ADT\FancyAdmin\DI\Injects\TabletServiceInject;
use ADT\FancyAdmin\DI\Injects\TabletSigningInject;
use ADT\FancyAdmin\DI\Injects\WebPushSenderInject;
use ADT\FancyAdmin\Model\Entities\Tablet;
use ADT\FancyAdmin\Model\Entities\TabletSignRequest;
use ADT\FancyAdmin\Model\Tablet\TabletSignDocument;
use ADT\FancyAdmin\UI\Components\Forms\BiometricSignature\BiometricSignatureInput;
use Nette\Application\Responses\FileResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Stránky tabletu (veřejné, bez přihlášení), docs/tablets.md: čekací stránka, podpis dokumentu
 * a poděkování. Tablet se jednou spáruje odkazem z QR kódu (pair), dostane klíč zařízení do
 * cookie a povolí notifikace (Web Push). Formulář mu pak pošle obsluha: čeká u serveru jako
 * žádost o podpis a čekací stránka si ho vyzvedne (takeForm!) a otevře - při načtení, po návratu
 * do popředí a když jí to řekne service worker (sw), kterého probudí push a který k tomu ukáže
 * notifikaci. Na tabletu, který drží zákazník, tak není přihlášený žádný účet.
 *
 * Stránky jsou samostatné - vlastní layout (@tablet.latte), styl i skripty servíruje presenter
 * (asset), nezávisí na layoutu ani bundlu projektu a vypadají ve všech projektech stejně.
 * Projekt dodává jen logo (getTabletLogoUrl()) a odkaz na zásady zpracování osobních údajů
 * (getTabletPrivacyPolicyUrl()).
 *
 * Podpis dokumentu (sign): náhled dokumentu a biometrický podpis k otevřené žádosti tabletu,
 * uložení dělá TabletSigning; co se podepisuje a podepsané PDF dodá TabletSignHandler projektu.
 * Bez něj akce sign není dostupná a tablet otevírá odkazy na stránky projektu (TabletService::send).
 *
 * Projekt: presenter s routou v rozsahu cookie tabletu (fancyadmin: tablet: cookiePath).
 */
trait TabletDevicePresenterTrait
{
	use TabletServiceInject;
	use TabletSigningInject;
	use WebPushSenderInject;

	/** Za jak dlouho se tablet po podpisu sám vrátí na čekací stránku (s) */
	private const int TABLET_RETURN_AFTER_SIGN = 10;

	/** Po jak dlouhé nečinnosti se tablet z podpisu vrátí na čekací stránku a žádost skončí (s) */
	private const int TABLET_SIGN_IDLE_RETURN = 300;

	/**
	 * Soubory, které stránky tabletu potřebují - název v adrese => soubor ve fancyadminu
	 * (assets/). Přibalené knihovny třetích stran viz assets/vendor/README.md.
	 */
	private const array TABLET_ASSETS = [
		'tablet.css' => 'tablet/tablet.css',
		'tablet.js' => 'js/tablet.js',
		'tabletSign.js' => 'js/tabletSign.js',
		'signature_pad.js' => 'vendor/signature_pad/signature_pad.min.js',
		'pdf.mjs' => 'vendor/pdfjs/pdf.min.mjs',
		'pdf.worker.mjs' => 'vendor/pdfjs/pdf.worker.min.mjs',
	];

	/** JS plátna podpisu z balíčku adt/biometric-signature (jedna verze s PHP) */
	private const string TABLET_BIOMETRIC_ASSET = 'biometric-signature.js';

	/** Žádost o podpis, kterou tablet zobrazuje (akce sign) */
	private ?TabletSignRequest $tabletSignRequest = null;

	// --- co dodává projekt ---------------------------------------------------------------------

	/** Logo nad obsahem stránek tabletu, null = bez loga */
	protected function getTabletLogoUrl(): ?string
	{
		return null;
	}

	/** Odkaz na zásady zpracování osobních údajů pod obsahem, null = bez odkazu */
	protected function getTabletPrivacyPolicyUrl(): ?string
	{
		return null;
	}

	/** Název aplikace tabletu: titulek stránek a název na ploše */
	protected function getTabletAppName(): string
	{
		return $this->translateTabletText('fcadmin.tablets.device.title');
	}

	/** Řádek pod názvem tabletu na čekací stránce (např. pobočka) */
	protected function getTabletSubtitle(Tablet $tablet): ?string
	{
		return null;
	}

	/**
	 * Ikony aplikace na ploše (čtvercové PNG) - bez ikon 192 a 512 px Chrome přidání na plochu
	 * nenabídne. První ikona slouží i jako apple-touch-icon.
	 *
	 * @return list<array{src: string, sizes: string, type?: string, purpose?: string}>
	 */
	protected function getTabletIcons(): array
	{
		return [];
	}

	/** Jazyk stránek tabletu (atribut lang) */
	protected function getTabletLang(): string
	{
		return 'cs';
	}

	/** Překlad textu, který se nevypisuje v šabloně (formulář podpisu, název aplikace) */
	protected function translateTabletText(string $message): string
	{
		return (string) $this->getTemplate()->getLatte()->invokeFilter('translate', [$message]);
	}

	// --- čekací stránka ------------------------------------------------------------------------

	public function actionDefault(): void
	{
		// Signály čekací stránky (registerPush!, takeForm!) jdou na její adresu
		// a Nette pouští akci před signálem. Dotaz na formulář ale není otevření čekací stránky:
		// returnToWaiting() by tablet znovu uvolnil a zrušil žádost, kterou právě zobrazuje.
		if ($this->getSignal() !== null) {
			return;
		}

		$tablet = $this->_tabletService->getCurrentTablet();
		if ($tablet !== null) {
			$this->_tabletService->returnToWaiting($tablet);
		}

		$this->prepareTabletPage();
		$this->template->tablet = $tablet;
		$this->template->tabletSubtitle = $tablet === null ? null : $this->getTabletSubtitle($tablet);
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
		$this->prepareTabletPage();
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

	// --- podpis dokumentu ----------------------------------------------------------------------

	/**
	 * Podpis dokumentu k žádosti, kterou tablet právě zobrazuje. Bez ní (jiné zařízení, tablet
	 * už je volný, žádost podepsaná nebo zrušená) zpět na čekací stránku - odkaz sám nic neotevře.
	 */
	public function actionSign(): void
	{
		[$request, $document] = $this->requireTabletSignDocument();

		$this->tabletSignRequest = $request;
		$this->prepareTabletPage();
		$this->template->document = $document;
		$this->template->idleSeconds = self::TABLET_SIGN_IDLE_RETURN;
		$this->setTabletDeviceTemplateFile(__DIR__ . '/sign.latte');
	}

	/** PDF dokumentu k náhledu na stránce podpisu - jen tabletu s otevřenou žádostí */
	public function actionDocument(): void
	{
		[, $document] = $this->requireTabletSignDocument();
		$pdf = $document->getPdf();
		if ($pdf === null) {
			$this->error();
		}

		$response = $this->getHttpResponse();
		$response->setContentType('application/pdf');
		$response->setHeader('Content-Disposition', 'inline; filename="document.pdf"');
		$response->setHeader('Cache-Control', 'no-store');
		$this->sendResponse(new TextResponse($pdf));
	}

	protected function createComponentTabletSignForm(): Form
	{
		$form = new Form();
		$signature = BiometricSignatureInput::addBiometricSignature($form, 'signature');
		$signature->setRequired($this->translateTabletText('fcadmin.tablets.sign.required'));
		$signature->setClearCaption($this->translateTabletText('fcadmin.biometricSignature.clear'));
		$signature->setInvalidMessage($this->translateTabletText('fcadmin.biometricSignature.invalid'));
		$form->addSubmit('submit');

		$form->onSuccess[] = function (Form $form): void {
			// dokument mezitím přestal jít podepsat - stránka po odeslání ukáže důvod
			if ($this->_tabletSigning->getDocument($this->tabletSignRequest)?->error !== null) {
				return;
			}

			/** @var SignatureCapture $capture */
			$capture = $form['signature']->getValue();
			$httpRequest = $this->getHttpRequest();

			try {
				$this->_tabletSigning->sign($this->tabletSignRequest, $capture, $httpRequest->getRemoteAddress(), $httpRequest->getHeader('User-Agent'));
			} catch (\Nette\Application\AbortException $e) {
				throw $e;
			} catch (\Throwable $e) {
				// podpis zůstal neověřený, žádost je dál otevřená - jde podepsat znovu
				Debugger::log($e, ILogger::EXCEPTION);
				$form->addError($this->translateTabletText('fcadmin.tablets.sign.failed'));

				return;
			}

			$this->redirect('done');
		};

		return $form;
	}

	/** Po podpisu: poděkování, za chvíli zpět na čekací stránku (tablet.js, data-tablet-return) */
	public function actionDone(): void
	{
		$this->prepareTabletPage();
		$this->template->returnAfter = self::TABLET_RETURN_AFTER_SIGN;
		$this->setTabletDeviceTemplateFile(__DIR__ . '/done.latte');
	}

	// --- soubory stránek a aplikace na ploše ---------------------------------------------------

	/**
	 * Styl a skripty stránek tabletu (TABLET_ASSETS) - stránky nezávisí na bundlu projektu.
	 * Adresa nese čas změny souboru (v), takže se smí ukládat do mezipaměti natrvalo.
	 */
	public function actionAsset(string $file): void
	{
		$path = $this->getTabletAssetPath($file);
		if ($path === null) {
			$this->error();
		}

		$response = $this->getHttpResponse();
		$response->setHeader('Cache-Control', $this->getHttpRequest()->getQuery('v') !== null ? 'public, max-age=31536000, immutable' : 'no-cache');
		$this->sendResponse(new FileResponse(
			$path,
			$file,
			str_ends_with($file, '.css') ? 'text/css' : 'text/javascript',
			false,
		));
	}

	/**
	 * Manifest webové aplikace - tablet má stránky používat jako aplikaci z plochy (display:
	 * standalone; iPad povolí notifikace jen takové). Čekací stránka otevřená v prohlížeči
	 * přidání na plochu nabízí (tablet.js).
	 */
	public function actionManifest(): void
	{
		$name = $this->getTabletAppName();
		$start = $this->link('default');

		$this->sendJson([
			'id' => $start,
			'name' => $name,
			'short_name' => $name,
			'lang' => $this->getTabletLang(),
			'start_url' => $start,
			'scope' => $start,
			'display' => 'standalone',
			'background_color' => '#f4f5f7',
			'theme_color' => '#f4f5f7',
			'icons' => $this->getTabletIcons(),
		]);
	}

	/** Stránky tabletu mají vlastní layout - stejný ve všech projektech */
	public function formatLayoutTemplateFiles(): array
	{
		return [__DIR__ . '/@tablet.latte'];
	}

	/** Co potřebuje layout tabletu: logo, odkaz na zásady, název, adresy stylu a skriptů */
	private function prepareTabletPage(): void
	{
		$assets = [];
		foreach ([...array_keys(self::TABLET_ASSETS), self::TABLET_BIOMETRIC_ASSET] as $file) {
			$path = $this->getTabletAssetPath($file);
			if ($path !== null) {
				$assets[$file] = $this->link('asset', ['file' => $file, 'v' => filemtime($path)]);
			}
		}

		$icons = $this->getTabletIcons();

		$this->template->tabletAssets = $assets;
		$this->template->tabletImportMap = Json::encode(['imports' => array_filter([
			'pdfjs' => $assets['pdf.mjs'],
			'signature_pad' => $assets['signature_pad.js'],
			'@biometric-signature/biometric-signature.js' => $assets[self::TABLET_BIOMETRIC_ASSET] ?? null,
		])]);
		$this->template->tabletLogoUrl = $this->getTabletLogoUrl();
		$this->template->tabletPrivacyPolicyUrl = $this->getTabletPrivacyPolicyUrl();
		$this->template->tabletAppName = $this->getTabletAppName();
		$this->template->tabletLang = $this->getTabletLang();
		$this->template->tabletAppleIconUrl = $icons[0]['src'] ?? null;
	}

	private function getTabletAssetPath(string $file): ?string
	{
		if (isset(self::TABLET_ASSETS[$file])) {
			return dirname(__DIR__, 4) . '/assets/' . self::TABLET_ASSETS[$file];
		}

		if ($file === self::TABLET_BIOMETRIC_ASSET && class_exists(SignatureCapture::class)) {
			$path = dirname((new \ReflectionClass(SignatureCapture::class))->getFileName(), 2) . '/assets/' . self::TABLET_BIOMETRIC_ASSET;

			return is_file($path) ? $path : null;
		}

		return null;
	}

	/**
	 * Otevřená žádost tabletu, ze kterého přišel požadavek, a co k ní tablet ukáže. Bez žádosti
	 * nebo když už nejde podepsat, končí požadavek na čekací stránce (ta tablet uvolní).
	 *
	 * @return array{TabletSignRequest, TabletSignDocument}
	 */
	private function requireTabletSignDocument(): array
	{
		if (!$this->_tabletSigning->isEnabled()) {
			$this->error();
		}

		$request = $this->_tabletService->getCurrentRequest();
		$document = $request === null ? null : $this->_tabletSigning->getDocument($request);
		if ($request === null || $document === null) {
			$this->redirect('default');
		}

		return [$request, $document];
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
