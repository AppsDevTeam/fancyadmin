# JS komponenty (adt-js-components 3.4) a Tom Select

Fancyadmin stojí na `adt-js-components` ^3.4 - bez jQuery pluginů, AJAX přes Naja, rozbalovací pole
přes [Tom Select](https://tom-select.js.org) místo Select2. Tady je, co se spouští samo, jak se to nastavuje
a jak převést projekt ze Select2.

## Co se spouští samo

Builtiny se nikde neregistrují ani nevolají (`init*()` z 2.x jsou pryč). Začnou hlídat dokument při prvním
`AdtJsComponents.registerModules()` - v `app.js` fancyadminu to dělá `_registerFancyadminComponents.js`, takže
projekt, který importuje `@fancyadmin/js/app`, nemusí nic. Každý builtin je vlastní chunk a načte se, až když
se v dokumentu objeví jeho prvek (i z překresleného snippetu nebo modalu); stránka bez něj z něj nenačte nic.

| Builtin | Spouští ho | Klíč v `jsComponentsConfig` |
|---|---|---|
| Select (Tom Select) | `select[data-adt-select]`, `select[data-adt-select2]`; ve fancyadminu každý select kromě `.select-default` (viz níž) | `select` |
| AjaxSelect | `[data-adt-ajax-select]` (adt/ajax-select) | `ajax-select` |
| Form | každý `<form>` - validace nette-forms, `window.Nette`; s `data-adt-submit-form` i spinner, zámek a chyby u polí | `submit-form` |
| Replicator | `[data-adt-replicator]` (kdyby/replicator) | `replicator` |
| DateInput | `[data-adt-date-input]` (flatpickr) | `date-input` |
| Chart | `canvas[data-adt-chart]` (Chart.js) | `chart` |
| CurrencyInput | `[data-adt-currency-input]` (AutoNumeric) | `currency-input` |
| RedrawSnippet | `[data-adt-redraw-snippet]` | `redraw-snippet` |
| InputClear | `[data-adt-input-clear]` | `input-clear` |
| Recaptcha, GLightbox, Map, GoogleMaps, Translate | `[data-adt-<klíč>]` | `recaptcha`, `glightbox`, `map`, `google-maps`, `translate` |

**Projekt `nette-forms` neimportuje** ani nenastavuje `window.Nette` - dělá to builtin Form (`app.js`
fancyadminu taky ne). Druhá kopie by formuláře inicializovala dřív než builtin a jeho chyby u polí by se při
odeslání nepoužily. `window.Nette` existuje až po načtení chunku Form, kód mimo formulář se na něj ptá
`window.Nette?.…`.

Datum na nativních inputech (`addDate()`, `addTime()`, `addDateTime()`) neobsluhuje builtin DateInput, ale
`@fancyadmin/js/dateInput` (viz [flatpickr.md](flatpickr.md)). Builtin DateInput je jen pro prvky
s `data-adt-date-input`; na jednom inputu se oba nekombinují.

## Nastavení

Volby builtinů jdou z `fancyadmin.jsComponentsConfig` do atributu `data-adt-js-components` na `<body>`
(JSON podle klíče z tabulky). Volby jednoho prvku jsou JSON v jeho atributu, např.
`data-adt-select='{"dropdownParent": "body"}'`. Atributy musí být platný JSON.

Fancyadmin přidává výchozí hodnoty `JsComponents::DEFAULTS`, projektová konfigurace je přepíše
(`array_replace_recursive`, `BasePresenterTrait::beforeRender()` volá `JsComponents::setProjectComponents()`):

```php
public const array DEFAULTS = [
	'select' => ['selector' => 'select:not(.select-default)'],
];
```

Tom Select tedy dostane **každý** select bez třídy `select-default`, stejně jako dřív Select2 - projekt nemusí
k polím přidávat `data-adt-select`. Nativní select zůstane s třídou `select-default` na selectu nebo na jeho
předkovi; selecty kalendáře flatpickru ji mají (jinak by se jejich seznam otvíral mimo kalendář).

### Přesměrování builtinu (`selector`)

Klíč `selector` u builtinu mění prvek, který ho spouští:

```neon
fancyadmin:
	jsComponentsConfig:
		select:
			# Tom Select jen na výslovně označených polích (výchozí chování adt-js-components)
			selector: 'select[data-adt-select], select[data-adt-select2]'
```

- U **Select** určuje i to, které selecty Tom Select dostanou.
- U ostatních builtinů mění jen to, kdy se chunk načte; obsluhují dál svůj `data-adt-*` atribut.
- Builtin nejde nahradit vlastním kódem pod stejným jménem. Vlastní komponenta má vlastní atribut - složka
  `<Name>/index.js` v globu `app` z `registerModules()` se spustí na `data-adt-<name-v-kebab-case>`, jiný selektor
  `AdtJsComponents.init('muj-selektor', 'Cesta/Ke/Komponente')`.

Hodnoty z PHP (překlady, URL) jdou přes `$this->_jsComponents->setComponents([...])` před `beforeRender()`
traitu, ten pak JSON vygeneruje. Slučuje se jen na nejvyšší úrovni (`array_merge`): jiné klíče zůstanou, ale
klíč, který je v `jsComponentsConfig` nebo `DEFAULTS` (`select`), se přepíše - volby builtinů proto patří do
`jsComponentsConfig`.

## Přechod ze Select2

### Balíčky

- `adt-js-components` na `^3.4`; `naja` jako peer dependency (fancyadmin ji inicializuje sám, viz README, AJAX).
- Pryč: `select2`, `select2-bootstrap-5-theme`, `nette.ajax.js`, `adt-nette-ajax`, `forms-replicator` - pokud
  je nepoužívá jiná část projektu. jQuery zůstává jen kvůli vlastnímu kódu projektu, knihovny ho nepotřebují.

### Atributy a volby

`data-adt-select2` funguje dál jako alias `data-adt-select` (posílá ho třeba `adt/datagrid-components`).
Volby Select2 se převádějí:

| Select2 | Tom Select |
|---|---|
| `placeholder`, `dropdownParent` | beze změny (`dropdownParent` jen jako řetězec, např. `"body"`) |
| `dropdownCssClass: 'x'` | `dropdownClass: 'ts-dropdown x'` |
| `tags: true` | `create: true` |
| `allowClear: true` | tlačítko „vymazat“ (`clear_button`); select s promptem Nette, který není povinný, ho má sám |
| `minimumResultsForSearch` (i `data-minimum-results-for-search`) | bez vyhledávacího pole, když je položek méně |
| `width`, `theme`, `language` | zahozeno (šířku a vzhled řeší styly, texty jazyk z `<html lang>`) |

Multiselect má tlačítko „odebrat“ u každé položky sám.

### JS

| Select2 | Tom Select (`el` je původní `<select>`) |
|---|---|
| `$(el).select2(...)` | nic - builtin se spustí sám; ručně `new TomSelect(el, {...})` jen mimo builtin |
| `$(el).val(v).trigger('change')` | `el.tomselect.setValue(v)` |
| `$(el).select2('open')` / `'close'` | `el.tomselect.open()` / `close()` |
| přidání `<option>` z JS | `el.tomselect.addOption({ value, text })`, po změně `<option>` v DOM `el.tomselect.sync()` |
| `$(el).select2('destroy')` | `el.tomselect.destroy()` |
| `select2:select`, `select2:unselect` | `el.tomselect.on('item_add' / 'item_remove', ...)`, nebo nativní `change` na selectu |
| `select2:open` | `el.tomselect.on('dropdown_open', ...)` |

Nativní `change` na původním selectu chodí dál, Nette validace i `RedrawSnippet` na něm fungují beze změny.
Inicializace v `onContentLoaded()` odpadá: builtin hlídá i prvky přidané později.

### Styly

| Select2 | Tom Select |
|---|---|
| `.select2-container` | `.ts-wrapper` (nese třídy původního selectu, i `.form-select` a velikost) |
| `.select2-selection` | `.ts-control` |
| `.select2-dropdown`, `.select2-results__option` | `.ts-dropdown`, `.ts-dropdown .option` |
| `.select2-container--focus` | `.ts-wrapper.focus` |

- Vzhled je v `assets/scss/_tomSelect.scss`. Téma Bootstrap 5 Tom Selectu se načítá jako CSS chunk **až za**
  styly aplikace, proto pravidla nesou předponu `:root body`. Projektové pravidlo, které je má přebít, potřebuje
  stejnou nebo vyšší specificitu.
- Seznam se vkládá do obalu pole (Select2 ho dával do `body`). V kontejneru s `overflow` (tabulka, karta,
  boční panel) ho to ořízne - takové pole potřebuje `data-adt-select='{"dropdownParent": "body"}'`. Seznam
  zavěšený do `body` přebírá výšku a písmo pole přes `@fancyadmin/js/tomSelectDropdownSize` (součást `app.js`).
- Filtr nad Tom Selectem (`.select-filter`) popisuje [tabs.md](tabs.md), selecty kalendáře [flatpickr.md](flatpickr.md).
