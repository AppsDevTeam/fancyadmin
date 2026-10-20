# Datum a čas (flatpickr)

Pole pro datum a čas používají [flatpickr](https://github.com/flatpickr/flatpickr) místo
nativních inputů, jejichž formát se řídí jazykem prohlížeče (anglický = `mm/dd/rrrr`, AM/PM).

Balíček dodává **vzhled i chování**, ale sám se nikde nezapne: `flatpickr` není jeho
závislost. Projekt si flatpickr nainstaluje a zapojí skin a init (jednou, viz workflow níž);
od té chvíle picker naskakuje na datová pole sám a formulář nemusí dělat nic.

---

## Co je kde

| soubor | co dělá |
|---|---|
| `assets/scss/_flatpickr.scss` | vzhled kalendáře; na prvním řádku si přes `@use` natahuje základní CSS flatpickru |
| `assets/js/dateInput.js` | inicializace pickeru, úklid instancí po AJAXu, zapojení obou pluginů |
| `assets/js/flatpickrYearSelect.js` | plugin: rok jako rozbalovací seznam |
| `assets/js/flatpickrTimeSelect.js` | plugin: hodiny a minuty jako rozbalovací seznamy |
| `assets/js/flatpickrDropdown.js` | panel se seznamem pro všechny tři selecty (měsíc, rok, čas) |

Žádný z těch souborů se nenačítá sám od sebe: `app.scss` skin neimportuje a `app.js`
`dateInput.js` taky ne. Projekt bez pickeru tak o flatpickru
neví a nic z něj nenese — a projekt s pickerem si obojí zapojí sám (flatpickr potřebuje JS
i build CSS). V `package.json` balíčku flatpickr není vůbec; standalone build balíčku na něj
nesáhne, protože je nenačítá ani `app.scss`, ani `app.js`. Kdo by chtěl `_flatpickr.scss`
přeložit přímo v repu fancyadminu, musí si flatpickr doinstalovat ručně.

---

## Zapojení v projektu (jednorázově)

**1. Závislost** — jediné místo, odkud se flatpickr stahuje:

```bash
yarn add flatpickr
```

**2. SCSS** — `@use` skinu do hlavního souboru projektu (v `app.scss` balíčku schválně
není, aby ho projekt bez pickeru nemusel kompilovat):

```scss
@use '…/vendor/adt/fancyadmin/assets/scss/flatpickr';
```

Základní CSS flatpickru si partial natahuje sám na prvním řádku — v projektu ho neimportuj.

**3. JS** — init na DOM ready i po AJAXu:

```js
import { Czech } from 'flatpickr/dist/l10n/cs.js';
import { initDateInput } from '@fancyadmin/js/dateInput';

const options = { locale: Czech, minuteIncrement: 15 };

$(function () { initDateInput(options); });
$.nette.ext('live').after(function () { initDateInput(options); });
```

Tím to končí — zbytek (pluginy, panel se seznamem) si `dateInput.js` natáhne sám.

---

## Použití ve formuláři

Markup — **nic**. Picker se nasadí na každý nativní `date` / `time` / `datetime-local`
input, tedy na všechno, co vyrobí `addDate()`, `addTime()` a `addDateTime()`:

```php
$form->addDateTime('startsAt', 'Od');   // picker, 24h, zobrazuje d.m.Y H:i
$form->addDate('issuedAt', 'Vystaveno');
```

Z typu inputu se odvodí `dateFormat` (formát, ve kterém hodnotu čeká Nette
`DateTimeControl` — `Y-m-d`, `H:i`, `Y-m-d\TH:i`, s vteřinami u `withSeconds`), české
zobrazení přes `altInput`/`altFormat` a `allowInput`. Atributy `min`/`max`, které Nette
vykresluje z pravidla `RANGE`, se mapují na `minDate`/`maxDate` (u času
`minTime`/`maxTime`).

### Přebití pro konkrétní pole

Atribut `data-flatpickr` (JSON) má přednost před odvozeným nastavením i před výchozím
z `initDateInput`. Hodí se na meze, které nejsou validačním pravidlem — typicky datum
narození, kde jde jen o rozsah roků v seznamu:

```php
$form->addDate('dateOfBirth', 'Datum narození')
    ->setHtmlAttribute('data-flatpickr', Json::encode([
        'minDate' => (new DateTimeImmutable('-120 years'))->format('Y-m-d'),
        'maxDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
    ]));
```

Stejný atribut zapne picker i na obyčejném `text` inputu (např. ovladač v toolbaru);
tam se neodvozuje nic, všechno řeknou options.

### Vypnutí pickeru

Třída `date-default` na inputu — stejná konvence jako `select-default` u Select2.
Pole pak zůstane nativní.

---

## Co picker umí navíc proti výchozímu flatpickru

| prvek | kdo ho vyrábí |
|---|---|
| měsíc | flatpickr sám (`monthSelectorType: "dropdown"` je výchozí) |
| rok | plugin `flatpickrYearSelect` — flatpickr ho renderuje jako číselné pole |
| hodiny a minuty | plugin `flatpickrTimeSelect` — seznam vedle číselného pole, které zůstává editovatelné |

Rozbalený seznam u všech tří nekreslí prohlížeč, ale `flatpickrDropdown.js`: na `mousedown`
zruší nativní seznam a vedle pole vykreslí vlastní panel (`.flatpickr-dropdown`). Důvod je
v šířce a umístění — nativní seznam je široký jako select (u času, kde je ze selectu vidět
jen šipka, tedy pár pixelů) a Firefox ho překlápí přes kalendář, aby vybraná položka seděla
na tlačítku. Select zůstává v DOM kvůli hodnotě, klávesnici a přístupnosti; šipkami jde
hodnotu měnit bez otevírání panelu.

Rozsah let se bere z `minDate`/`maxDate` (ty můžou přijít i z atributů `min`/`max`),
jinak je to aktuální rok ±10. **Pro pole typu „datum narození" je proto potřeba nastavit
`minDate`,** jinak se starší ročník nedá vybrat ze seznamu.

Krok minut se bere z `minuteIncrement` instance (výchozí flatpickr = 5). Má odpovídat
mřížce, ve které projekt plánuje — jinak jdou vybrat časy, které v kalendáři neexistují.
V adt-dent je to 15 minut (`SLOT_MINUTES` v `calendar-common.js`).

---

## Na co si dát pozor

**Neimportuj v projektu `flatpickr/dist/flatpickr.css`.** Skin si ho tahá sám a druhá kopie
by podle pořadí ve svazku mohla náš vzhled přebít.

**Specificita.** Základní CSS flatpickru cílí na prvky dvěma třídami
(`.flatpickr-current-month .flatpickr-monthDropdown-months`). Pravidla ve skinu proto musí mít
aspoň tři, jinak tiše spadnou na vendor vzhled — stalo se to při přesunu společného bloku
o úroveň výš (zmizela šipka selectu a odsazení položek).

**Select2.** Každý `<select>` uvnitř kalendáře musí mít třídu `select-default`, jinak si ho
převezme globální Select2 a otevře jeho seznam mimo kalendář. Pluginy si ji přidávají samy,
u měsíce to dělá `dateInput.js`.

**Panel se seznamem** se vkládá do `fp.calendarContainer` a pozici dostává v proměnných
`--dropdown-top` / `--dropdown-left` (počítá ji JS z polohy pole v okně). Pod pole se vejde
skoro vždy; nad něj se překlápí, jen když dole není místo a nahoře ano.

**Projekt bez pickeru** nenese nic: bez `@use` skinu a bez importu `dateInput.js` se do
svazku nedostane ani CSS, ani JS, a flatpickr nemusí být vůbec nainstalovaný.
