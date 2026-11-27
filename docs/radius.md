# Zaoblení

Jedna škála pro ovládací prvky i plochy v `assets/scss/_radius.scss`, jako CSS proměnné Bootstrapu `--bs-border-radius-*` (xxs a xs doplňuje fancyadmin). Prvek i plocha berou stupeň podle své velikosti, vnořená plocha má stupeň menší než ta, ve které leží.

| Stupeň | Co | square | default | round |
|---|---|---|---|---|
| `xxs` | tečky legendy, čtverečky v kalendáři | 2 | 4 | 6 |
| `xs` | položky uvnitř ploch (menu, seznamy, den v kalendáři), tooltip | 2 | 8 | 12 |
| `sm` | malý prvek, box v kartě | 2 | 12 | 16 |
| výchozí | prvek, panel, karta gridu, plovoucí vrstva | 2 | 14 | 20 |
| `lg` | velký prvek, karta, widget, modal | 2 | 18 | 24 |
| `xl` | velký kontejner (přihlášení, chat) | 2 | 24 | 32 |
| `xxl` | stránková karta | 2 | 32 | 40 |
| `pill` | badge, chip, záložky, stránkování | 2 | pilulka | pilulka |

Ovládací prvky (pole, select, Tom Select, tlačítka) mají `--control-radius-sm` / `-md` / `-xl` (velikost `sm` / výchozí / `xl`), ve výchozím režimu stupně `sm` / výchozí / `lg`. Plovoucí vrstvy (dropdowny, Tom Select, flatpickr, popovery) mají `--overlay-radius`, kulatá ikonová tlačítka `--round-button-radius`.

## Režim zaoblení

```neon
fancyadmin:
	radius: default # square | default | round
```

Layout vypíše volbu jako `<html data-radius="…">` a bloky `:root[data-radius=…]` (mixin `radius-modes`) vymění hodnoty škály. Přepíná se tak za běhu, jeden zkompilovaný bundle slouží projektům s různým režimem.

- `square`: všechny stupně 2 px, i pilulka.
- `round`: větší škála, ovládací prvky jako pilulka. Plochy pilulku nedostanou, uřízly by si obsah v rozích; pole, které roste do více řádků (`textarea.form-control`, Tom Select s více hodnotami), má výchozí stupeň.
- Kolečka (avatary, tečky, ikony v kruhu) mají `border-radius: 50%` a režim je nemění. Kulaté ikonové tlačítko má `var(--round-button-radius)`: kolečko, v režimu `square` hranaté jako ostatní tlačítka. Dráha přepínače (`.form-switch`, přepínač v akcích bočního panelu) zůstává pilulkou, jezdec je kolečko.

Stránky tabletu mají vlastní styl (`assets/tablet/tablet.css`) se stejnými režimy, hodnotu dodá projekt v `getTabletRadius()` presenteru tabletu.

## Pravidla

- Zaoblení psát jen přes `var(--bs-border-radius-*)`, `var(--control-radius*)` nebo `var(--overlay-radius)`. Sass proměnná ani číslo natvrdo se v režimu nepřepne. Výjimky jsou kolečko `50%` a pilulka, která má zůstat v každém režimu, `50rem`.
- `--control-radius` nastavuje prvek (pole, tlačítko, Tom Select, input-group); vlastní prvek mimo ně bere `var(--control-radius-md)`, jinak je bez zaoblení.
- Odvozená proměnná ze škály, která má režim sledovat, patří na `:root` do mixinu `radius-root-vars`. Spočítaná na jiném prvku by zůstala na hodnotě výchozího režimu.
- Projekt, který nenačítá celý fancyadmin (klientská zóna), převezme škálu i režimy z partialu: `$form-check-input-border-radius: var(--bs-border-radius-xxs)` před Bootstrapem, za ním `@include radius-root-vars` na `:root`, `@include radius-modes` a `@include radius-bootstrap-fixes` (`.btn-close`, `kbd`), a `data-radius` vypíše ve svém layoutu.
- Cizí knihovny se zaoblením natvrdo napojuje fancyadmin přebitím: widget `adt-ai-chat` v `_aiChat.scss`, Summernote v `_summernote.scss` (projekt, který editor zavádí sám, ho importuje vedle CSS knihovny), kalendář `@event-calendar` si zaobluje projekt.
