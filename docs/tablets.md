# Tablety pro vyplnění a podpis formulářů

Tablet, který drží zákazník (pacient, klient), se do administrace **nepřihlašuje**. Jednou se
spáruje QR kódem a od té doby mu obsluha posílá formuláře k vyplnění a podpisu. Spárování
platí, dokud ho obsluha neodpojí nebo dokud na tabletu někdo nesmaže data prohlížeče.

```
Administrace → Tablety → Spárovat ─ QR kód ─► tablet otevře odkaz, dostane klíč zařízení (cookie)
                                               a povolí notifikace (Web Push)
Obsluha: „Poslat na tablet" ─► odkaz čeká u serveru ─ prázdný push ─► service worker tabletu
                                                                       si odkaz vyzvedne a otevře
Tablet po odeslání / nečinnosti ─► čekací stránka ─► projekt zneplatní odkaz ($onRelease)
```

## Bezpečnost

- **Párovací token** je jednorázový, platí 15 minut (`TabletService::PAIRING_LIFETIME`) a v DB
  je jen jeho SHA-256. Nové spárování zneplatní klíč i odběr předchozího zařízení.
- **Klíč zařízení** má 256 bitů z CSPRNG, je v cookie `HttpOnly`, `SameSite=Lax` a v DB je jen
  jeho SHA-256. Obsluha může tablet kdykoli odpojit.
- **Push je prázdný**: jen probudí service worker a ten si odkaz vyzvedne signálem `takeForm!`
  s cookie tabletu. Odkaz tak nejde přes push službu prohlížeče (Google, Apple) a zpráva se
  nemusí šifrovat. Čekající odkaz se vydá jen jednou a jen do 10 minut
  (`PENDING_FORM_LIFETIME`).
- **Adresa odběru** se přijme jen https na známou push službu (`WebPushSender::isValidEndpoint`,
  ochrana před SSRF).
- **Service worker** má CSP `default-src 'none'; connect-src 'self'` a otevírá jen odkazy na
  stejný web.
- **Odkaz na formulář patří projektu** a ten ho musí zabezpečit (viz Vázání odkazu na tablet).
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

### Čekací stránka tabletu (veřejná)

Presenter projektu s veřejným layoutem (bez přihlášení), routa v rozsahu `cookiePath`:

```php
class TabletPresenter extends Presenter   // AutowireProperties (kdyby/autowired)
{
	use \ADT\FancyAdmin\UI\Presenters\TabletDevice\TabletDevicePresenterTrait;
}
```

Akce `default` (čekací stránka), `pair` (odkaz z QR), `sw` (service worker), signály
`registerPush!` a `takeForm!`. Signály workeru jdou na adresu `sw` (`sw?do=takeForm`), akce
`sw` je proto při signálu přeskočí a odpoví až signál (JSON). Šablony `default.latte` a
`pair.latte` (bloky `title` a `content`) jde přebít vlastními u presenteru projektu. Do bundlu veřejné části:

```scss
@use '@fancyadmin/scss/tablet-device';
```

```js
if (document.querySelector('[data-tablet-device], [data-tablet-return], [data-tablet-idle-return]')) {
	import('@fancyadmin/js/tablet').then(({ initTabletPage }) => initTabletPage());
}
```

### Odeslání formuláře

```php
$error = $this->_tabletService->sendForm($tablet, $url, 'Souhlas se zpracováním údajů');
// null = odesláno, jinak hláška pro obsluhu (co udělat dál)
```

Nabídka tabletů: `$tabletQueryFactory->create()->byCanReceiveForms()->fetch()`.

### Vázání odkazu na tablet

Fancyadmin neví, co odkaz otevírá - zabezpečit ho musí projekt:

1. **Odkaz poslaný na tablet platí jen na něm.** Při odeslání si projekt k tokenu odkazu uloží
   tablet a na stránce formuláře ověří, že požadavek přišel z něj:
   `$this->_tabletService->getCurrentTablet() === $tokenTablet`, jinak „odkaz neplatí".
2. **Krátká platnost** (desítky minut, ne dny jako u odkazu do e-mailu).
3. **Zneplatnění, když je tablet volný**: `TabletService::$onRelease` se volá, když se tablet
   vrátí na čekací stránku (po odeslání formuláře nebo po nečinnosti), při novém spárování
   a odpojení. Tam projekt zneplatní tokeny svázané s tabletem:

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

## Provoz tabletu

- **iPad**: stránku přidat na plochu (Sdílet → Přidat na plochu) a otevírat z plochy - jinak
  Safari notifikace nepovolí. **Android**: Chrome.
- Tablet zamknout v aplikaci: iPad **Řízený přístup** (Zpřístupnění), Android **připnutí
  obrazovky**. Zákazník pak z čekací stránky a formuláře neodejde do prohlížeče ani nastavení.
- Lokálně se self-signed certifikátem prohlížeč service worker nezaregistruje - ověřovat v
  Chromu s `--ignore-certificate-errors` (headless: povolení notifikací
  `Browser.grantPermissions`).
