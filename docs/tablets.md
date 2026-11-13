# Tablety pro vyplnění a podpis formulářů

Tablet, který drží zákazník (pacient, klient), se do administrace **nepřihlašuje**. Jednou se
spáruje QR kódem a od té doby mu obsluha posílá formuláře k vyplnění a podpisu. Spárování
platí, dokud ho obsluha neodpojí nebo dokud na tabletu někdo nesmaže data prohlížeče.

```
Administrace → Tablety → Spárovat ─ QR kód ─► tablet otevře odkaz, dostane klíč zařízení (cookie)
                                               a povolí notifikace (Web Push)
Obsluha: „Poslat na tablet" ─► odkaz čeká u serveru ─ prázdný push ─► service worker tabletu
                                                                       ukáže notifikaci, čekací
                                                                       stránka si odkaz vyzvedne
                                                                       a otevře
Tablet po odeslání / nečinnosti ─► čekací stránka ─► projekt zneplatní odkaz ($onRelease)
```

## Push a vyzvednutí formuláře

Každé odeslání je **žádost o podpis** (`TabletSignRequest`, vlastní tabulka, viz Žádosti
o podpis). Nevyzvednutá žádost čeká u serveru s odkazem (`takeForm!`), který se vydá **jen
jednou** a jen 10 minut (`PENDING_FORM_LIFETIME`). Vyzvedává a otevírá ho **čekací stránka** -
zeptá se serveru:

- **po načtení** (i po obnovení tlačítkem Obnovit - aplikace z plochy jiné obnovení nemá),
- **po návratu do popředí** (tablet se probudil, klepnutí na notifikaci otevřelo aplikaci),
- **na pokyn service workeru** po push zprávě.

V rozestupu se stránka sama neptá. Push (Web Push, VAPID) je prázdná zpráva, která probudí
service worker: ten ukáže notifikaci a otevřené stránce řekne, ať se zeptá hned - svítící
stránka tak formulář otevře sama, uspaný tablet po klepnutí na notifikaci.

Service worker se na formulář jen **dívá** (`takeForm!` s `peek=1`), aby věděl, co napsat do
notifikace - nevyzvedává ho a neotevírá. Otevřít odkaz totiž spolehlivě neumí: iOS po klepnutí
na notifikaci otevře aplikaci na čekací stránce (start_url), ne na odkazu z workeru, a formulář
vyzvednutý workerem by pak stránka už nedostala. Notifikace se ukazuje ke každé push zprávě
(Safari by jinak odběr zrušil); stránka ji po vyzvednutí formuláře zavře, u svítící stránky
tak jen problikne.

Formulář čeká na každý spárovaný tablet, i když push neodešel - `sendForm()` obsluze řekne, co dál:

| Tablet | `sendForm()` |
|---|---|
| nespárovaný | hláška, nic se neposílá |
| notifikace povolené, push odešel | `null` (odesláno) |
| bez notifikací / odběr už neplatí / push selhal / chybí klíč VAPID | hláška „otevřete nebo obnovte na tabletu čekací stránku"; formulář **čeká** (10 minut) a po načtení stránky se zobrazí |

## Žádosti o podpis

Co se na tablet posílá, se neukládá k tabletu, ale jako řádek tabulky žádostí. Tablet podle
ní pozná, že má co otevřít, a zároveň je to **evidence**: kdo, kdy a co na který tablet poslal,
kdy si to tablet otevřel a kdy se podepsalo.

| Sloupec | Význam |
|---|---|
| `tablet`, `tabletName` | na který tablet (název zůstane i po smazání tabletu) |
| `url`, `title` | odkaz, který tablet otevře, a název do notifikace |
| `type`, `subjectId`, `data` | co se podepisuje: druh dokumentu (kód projektu), ID záznamu a další údaje (JSON) - obecně, bez vazby na entity projektu |
| `requesterId`, `requesterName` | kdo žádost poslal |
| `createdAt`, `expiresAt` | odesláno a do kdy si ji tablet může vyzvednout |
| `openedAt` | tablet si ji vyzvedl a zobrazuje ji |
| `signedAt` | podepsáno (`TabletService::finish()`) |
| `cancelledAt` | zrušeno: tablet se vrátil na čekací stránku, byl znovu spárován nebo odpojen, nebo dostal jinou žádost |

Stav (`getState()`: čeká, otevřená, podepsaná, zrušená, propadlá) se neukládá, plyne z časů.
Tablet má nejvýš jednu nevyřízenou žádost - nová zruší předchozí. Žádosti se nemažou.

## Bezpečnost

- **Párovací token** je jednorázový, platí 15 minut (`TabletService::PAIRING_LIFETIME`) a v DB
  je jen jeho SHA-256. Nové spárování zneplatní klíč i odběr předchozího zařízení.
- **Klíč zařízení** má 256 bitů z CSPRNG, je v cookie `HttpOnly`, `SameSite=Lax` a v DB je jen
  jeho SHA-256. Obsluha může tablet kdykoli odpojit.
- **Vyzvednutí** (`takeForm!`) je POST ověřený cookie tabletu - stránka i service worker.
  Bez platné cookie odpoví 400 a stránka se načte znovu (ukáže, že tablet není spárovaný).
- **Push je prázdný**: jen probudí service worker, odkaz si tablet vyzvedne signálem `takeForm!`
  s cookie tabletu. Odkaz tak nejde přes push službu prohlížeče (Google, Apple) a zpráva se
  nemusí šifrovat. Čekající odkaz se vydá jen jednou a jen do 10 minut
  (`PENDING_FORM_LIFETIME`).
- **Adresa odběru** se přijme jen https na známou push službu (`WebPushSender::isValidEndpoint`,
  ochrana před SSRF).
- **Service worker** má CSP `default-src 'none'; connect-src 'self'` a otevírá jen odkazy na
  stejný web.
- **Odkaz na vlastní formulář patří projektu** a ten ho musí zabezpečit (viz Stránka formuláře na tabletu).
  Bez toho by odkaz otevřený na tabletu šel otevřít i jinde a zůstal by v historii prohlížeče
  sdíleného tabletu.

## Zapojení do projektu

### Konfigurace

```neon
fancyadmin:
	webPush:
		vapidPrivateKey: %env.WEB_PUSH_VAPID_PRIVATE_KEY%   # '' = push se neposílá
		subject: 'mailto:provoz@example.com'
	tablet:
		enabled: true
		cookieName: fancyadmin_tablet   # výchozí
		cookiePath: /                   # výchozí; = rozsah service workeru
		signRequestEntity: App\Model\Entities\TabletSignRequest   # entita žádosti o podpis
```

Privátní klíč VAPID je PEM klíče EC P-256 v base64 (jeden řádek do `.env`, na produkci šifrovaně):

```bash
openssl ecparam -name prime256v1 -genkey -noout | base64 | tr -d '\n'
```

Výměna klíče zneplatní všechny odběry, tablety pak musí notifikace povolit znovu (čekací
stránka to udělá sama při dalším otevření).

`cookiePath` musí pokrýt čekací stránku **i** stránky formulářů: service worker smí přesměrovat
jen stránky ve svém rozsahu a projekt na stránce formuláře čte cookie tabletu.

### Entita, query, grid, formulář

Stejně jako u ostatních modulů fancyadminu (např. MCP tokeny) - rozhraní a traity jsou ve
fancyadminu, třídy v projektu:

```php
#[ORM\Entity]
#[ORM\Table(name: 'tablet')]
class Tablet extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Tablet
{
	use \ADT\FancyAdmin\Model\Entities\TabletTrait;
}

// ve stejném jmenném prostoru jako Tablet, konstruktor bez povinných parametrů
#[ORM\Entity]
#[ORM\Table(name: 'tablet_sign_request')]
class TabletSignRequest extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\TabletSignRequest
{
	use \ADT\FancyAdmin\Model\Entities\TabletSignRequestTrait;
}

class TabletQuery extends BaseQuery implements \ADT\FancyAdmin\Model\Queries\TabletQuery
{
	use \ADT\FancyAdmin\Model\Queries\TabletQueryTrait;
}

class TabletGrid extends BaseGrid implements \ADT\FancyAdmin\UI\Components\Grids\Tablet\TabletGrid
{
	use BaseGridTrait;
	use TabletGridTrait;
}

class TabletForm extends EntityForm implements \ADT\FancyAdmin\UI\Components\Forms\Tablet\TabletForm
{
	use TabletFormTrait;
}
```

```neon
services:
	tabletQueryFactory:
		implement: App\Model\Queries\Factories\TabletQueryFactory   # extends fancyadminové
		factory: App\Model\Queries\TabletQuery
	tabletGridFactory:
		implement: App\UI\Components\Grids\Tablet\TabletGridFactory
		factory: App\UI\Components\Grids\Tablet\TabletGrid
	tabletFormFactory:
		implement: App\UI\Components\Forms\Tablet\TabletFormFactory
		factory: App\UI\Components\Forms\Tablet\TabletForm
```

Tabulku vytvoří migrace projektu (`migrations-diff`).

### Správa tabletů (administrace)

```php
class TabletPresenter extends AuthPresenter
{
	use TabletsPresenterTrait;

	protected function getTabletPairingUrl(string $token): string
	{
		return $this->link('//:Front:Tablet:pair', ['token' => $token]);
	}

	protected function getTabletQrSvg(string $url): string
	{
		// knihovnu na QR kód má projekt, např. mpdf/qrcode
		return (new \Mpdf\QrCode\Output\Svg())->output(new \Mpdf\QrCode\QrCode($url), 300, 'white', 'black');
	}

	// Párování, odpojení a úpravy jen s právem (výchozí: kdo vidí stránku)
	public function canEditTablets(): bool
	{
		return $this->getUser()->isAllowed('tablet.edit');
	}
}
```

Styly (`_tablets.scss`) jsou v `@fancyadmin/scss/app`.

### Stránky tabletu (veřejné)

```php
// routa v rozsahu cookie tabletu (cookiePath), např. /tablet[/<action>]
class TabletPresenter extends BasePresenter
{
	use \ADT\FancyAdmin\UI\Presenters\TabletDevice\TabletDevicePresenterTrait;

	// jediné, čím se stránky tabletu mezi projekty liší
	protected function getTabletLogoUrl(): ?string
	{
		return $this->basePath . '/images/logo.svg';
	}

	protected function getTabletPrivacyPolicyUrl(): ?string
	{
		return 'https://www.example.com/zpracovani-osobnich-udaju'; // null = bez odkazu
	}
}
```

Stránky jsou **samostatné**: vlastní layout (`@tablet.latte`), styl (`assets/tablet/tablet.css`)
i skripty servíruje presenter (akce `asset`), takže nezávisí na layoutu, Bootstrapu ani bundlu
projektu a ve všech projektech vypadají a fungují stejně. Knihovny třetích stran, které
potřebují (`signature_pad`, `pdf.js`), jsou přibalené ve fancyadminu (`assets/vendor/`).
Projekt nic neimportuje do svého bundlu.

| Akce | Co dělá |
|---|---|
| `default` | čekací stránka: stav notifikací, vyzvednutí formuláře, nabídka přidání na plochu, tlačítko Obnovit |
| `pair` | odkaz z QR kódu - spáruje zařízení a přesměruje na čekací stránku |
| `sw` | service worker (push → notifikace → pokyn stránce) |
| `sign`, `document`, `done` | podpis dokumentu, PDF k náhledu a poděkování - viz Podpis dokumentu |
| `asset`, `manifest` | styl a skripty stránek, manifest aplikace na ploše |

Signály `registerPush!` a `takeForm!`. Signály workeru jdou na adresu `sw` (`sw?do=takeForm`),
akce `sw` je proto při signálu přeskočí a odpoví až signál (JSON). Stejně se při signálu
přeskočí akce `default` - dotaz není otevření čekací stránky a tablet se jím neuvolňuje.

Co může projekt přepsat (metody presenteru): `getTabletLogoUrl()`, `getTabletPrivacyPolicyUrl()`,
`getTabletAppName()` (titulek a název na ploše), `getTabletSubtitle($tablet)` (řádek pod názvem
tabletu, např. pobočka), `getTabletIcons()` (ikony aplikace na ploše - bez ikon 192 a 512 px
Chrome přidání na plochu nenabídne), `getTabletLang()` a `translateTabletText()`.

### Odeslání formuláře

```php
$result = $this->_tabletService->send(
	$tablet,
	$url,                                   // stránka formuláře, kterou tablet otevře
	'Souhlas se zpracováním údajů',
	fn (TabletSignRequest $request) => $request
		->setSubject('consent', (string) $client->getId())      // co se podepisuje
		->setRequester((string) $user->getId(), $user->getName()), // kdo to poslal
);
$result->request;  // založená žádost, null = tablet není spárovaný
$result->message;  // null = odesláno, jinak hláška pro obsluhu (co udělat dál)
```

`sendForm($tablet, $url, $nazev)` je zkratka bez údajů žádosti, vrací jen hlášku. Nenulová
hláška neznamená, že formulář propadl - u spárovaného tabletu čeká na otevření nebo obnovení
čekací stránky (viz Push a vyzvednutí formuláře). Obsluze ji stačí zobrazit.

Nabídka tabletů: `$tabletQueryFactory->create()->byPaired()->fetch()` (formulář jde poslat na
každý spárovaný tablet); `->byCanReceiveForms()` vybere jen ty s povolenými notifikacemi.

### Stránka formuláře na tabletu

Podpis dokumentu řeší fancyadmin celý (viz Podpis dokumentu). Na tablet jde ale poslat i odkaz
na vlastní stránku projektu (dotazník k vyplnění apod.) - tu dělá projekt a řídí se žádostí:

1. **Odkaz platí jen na tabletu, kterému byl poslán, a jen dokud ho zobrazuje.** Stránka si
   vezme otevřenou žádost tabletu, ze kterého přišel požadavek:
   `$request = $this->_tabletService->getCurrentRequest()` - `null` (jiné zařízení, tablet už
   je volný, žádost podepsaná nebo zrušená) = „odkaz neplatí", zpět na čekací stránku. Co se
   podepisuje, vezme z `getType()`, `getSubjectId()` a `getData()`, ne z adresy.
2. **Po podpisu** `$this->_tabletService->finish($request)` - žádost je vyřízená a tablet už
   nic nezobrazuje.
3. **Když je tablet volný**, žádost se zruší sama: tablet se vrátil na čekací stránku (po
   nečinnosti, tlačítkem Zpět), byl znovu spárován nebo odpojen. Pro další úklid projektu je
   `TabletService::$onRelease`:

   ```neon
   decorator:
   	ADT\FancyAdmin\Model\Tablet\TabletService:
   		setup:
   			- '$onRelease[]' = [@App\Model\FormLinks, invalidateForTablet]
   ```

4. **Návrat na čekací stránku**: stránka formuláře na tabletu dostane
   `<div data-tablet-idle-return="{link :Front:Tablet:default}" data-tablet-idle-seconds="300">`
   (po nečinnosti zpět, stránka obnovená tlačítkem Zpět se načte znovu ze serveru), stránka
   po odeslání `<a href="{link :Front:Tablet:default}" class="btn tablet-return" data-tablet-return="10">`
   (za 10 s zpět). Zpět se jde přes `location.replace()`, formulář v historii nezůstane.
   Skript k tomu: `import { initTabletPage } from '@fancyadmin/js/tablet'` v bundlu projektu,
   nebo přímo modul z presenteru tabletu (`{link :Front:Tablet:asset, file => 'tablet.js'}`).

### Podpis dokumentu

Fancyadmin umí dokument na tabletu rovnou podepsat - stránka s náhledem a podpisem, uložení
podpisu i evidence jsou stejné ve všech projektech. Projekt dodá jen to, co se podepisuje,
a podepsané PDF. Potřebuje balíček `adt/biometric-signature` (`docs/biometric-signature.md`).

```neon
fancyadmin:
	tablet:
		signedDocumentEntity: App\Model\Entities\SignedDocument   # zapíná podpis dokumentu

services:
	- App\Model\Signing\DocumentSignHandler   # implements ADT\FancyAdmin\Model\Tablet\TabletSignHandler
```

```php
// ve stejném jmenném prostoru jako TabletSignRequest a File, konstruktor bez povinných parametrů
#[ORM\Entity]
#[ORM\Table(name: 'signed_document')]
class SignedDocument extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\SignedDocument
{
	use \ADT\FancyAdmin\Model\Entities\SignedDocumentTrait;
}
```

**Podepsaný dokument** (`signed_document`) je jeden řádek za každý podpis: biometrický podpis
(obrázek, záznam průběhu psaní, důkazní záznam a jeho otisk), vazba na žádost o podpis,
podepsané PDF (soubor `adt/files`) a obecně to, čeho se podpis týká - `type`, `subjectId`,
`data` převzaté ze žádosti. Entity projektu se na podepsaný dokument jen odkazují (cizím
klíčem na `signed_document`, nebo ho dohledají podle `type` + `subjectId`).

Odeslání k podpisu: `TabletService::send()` s odkazem na akci `sign` presenteru tabletu
a s tím, co se podepisuje (`setSubject()`). Stránka `sign` pak:

1. vezme otevřenou žádost tabletu (`getCurrentRequest()`) - bez ní zpět na čekací stránku,
2. zeptá se projektu, co ukázat (`TabletSignHandler::getDocument()`): název, PDF k náhledu
   (vykreslí se po stránkách pod sebou), případně text nebo důvod, proč podepsat nejde,
3. po podpisu (`TabletSigning::sign()`) uloží podepsaný dokument, dokončí podpis bez kódu
   (tablet patří provozovateli, totožnost ověřil personál - `completeOnSite()`), nechá projekt
   vygenerovat a uložit podepsané PDF (`createSignedPdf()`), žádost označí jako podepsanou
   a zavolá `onSigned()` (e-mail, změna stavu záznamu),
4. přejde na poděkování (`done`), odkud se tablet sám vrátí na čekací stránku.

```php
class DocumentSignHandler implements TabletSignHandler
{
	public function getDocument(TabletSignRequest $request): ?TabletSignDocument
	{
		$contract = $this->contracts->find((int) $request->getSubjectId());
		if ($contract === null) {
			return null; // není co podepsat - tablet se vrátí na čekací stránku
		}

		return new TabletSignDocument($contract->getTitle(), fn (): string => $contract->getUnsignedPdf());
	}

	public function getEvidenceSubject(SignedDocument $document): array
	{
		// do důkazního záznamu: kdo podepisuje, otisk originálu …
		return ['client' => ['id' => 17, 'name' => 'Jan Novák']];
	}

	public function createSignedPdf(SignedDocument $document): ?SignedPdf
	{
		// obrázek podpisu $document->getImageDataUri(), otisk do PDF $document->getDocumentHash()
		$pdf = $this->pdfGenerator->render($document);
		$file = $this->files->save($pdf, 'smlouva.pdf'); // adt/files

		return new SignedPdf($file, $pdf);
	}

	public function onSigned(SignedDocument $document): void
	{
		// e-mail klientovi, změna stavu smlouvy …
	}
}
```

Bez handleru nebo entity je podpis vypnutý (`TabletSigning::isEnabled()`), akce `sign` vrací
404 a tablet otevírá stránky formulářů projektu (viz Stránka formuláře na tabletu).

## Provoz tabletu

- **iPad**: stránku přidat na plochu (Sdílet → Přidat na plochu) a otevírat z plochy - jinak
  Safari notifikace nepovolí. **Android**: Chrome.
- Tablet bez notifikací funguje taky, jen ho nejde probudit a formulář se na něm ukáže až po
  načtení čekací stránky: po odeslání na ní klepnout na Obnovit (nebo ji otevřít).
- Tablet zamknout v aplikaci: iPad **Řízený přístup** (Zpřístupnění), Android **připnutí
  obrazovky**. Zákazník pak z čekací stránky a formuláře neodejde do prohlížeče ani nastavení.
- Lokálně se self-signed certifikátem prohlížeč service worker nezaregistruje - ověřovat v
  Chromu s `--ignore-certificate-errors` (headless: povolení notifikací
  `Browser.grantPermissions`).
