# Filtr v hlavičce sloupce

Náhrada tlačítka **Filtry** a řádku filtrů nad gridem (`CollapsibleFiltersTrait`, viz `datagrid-filters.md`).
Klik na název sloupce otevře malý panel (Bootstrap dropdown) s polem filtru jen pro tento sloupec. Pod lištou
je pruh aktivních filtrů: počet „X z Y záznamů“, čipy s hodnotou a křížkem a odkaz „Zrušit filtry“. Výchozí filtr
gridu může být přepínač v liště („Jen aktivní“).

## Použití

Trait do potomka `ADT\Datagrid\Component\DataGrid` místo `CollapsibleFiltersTrait`:

```php
use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\ColumnFiltersTrait;

class AppDataGrid extends DataGrid
{
	use ColumnFiltersTrait;
}
```

Šablona projektu dál dědí z `{extends $originalTemplate}` a obsah lišty (hromadné akce, vlastní tlačítka) přidá
blokem `toolbar`, stejně jako u `CollapsibleFiltersTrait`.

Počet bez filtrů grid nezná, dodá ho vlastník gridu (typicky nový query objekt bez filtrů), a projekt může
přejmenovat „záznamy“:

```php
$grid->setUnfilteredCountCallback(fn(): int => $this->createUnfilteredQuery()->count());
$grid->setCountTranslation('app.orderGrid.count'); // '{1}%formatted% objednávka|[2,4]…|[5,+Inf[%formatted% objednávek'
```

Výchozí filtr jako přepínač v liště (volá se po `setDefaultFilter()`, jen výběrový filtr):

```php
$grid->setDefaultFilter(['active' => 1]);
$grid->addDefaultFilterToggle('active', 'app.countryGrid.onlyActive'); // popisek podle výchozí hodnoty
```

JS a styly:

```js
import { configureColumnFilters } from '@fancyadmin/js/columnFilters';
configureColumnFilters({ locale: Czech }); // jazyk kalendáře období
```

`_datagridColumnFilters.scss` je součástí `app.scss`.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Grids/ColumnFiltersTrait.php` | šablona, filtry do panelů, čipy, hodnoty čipů, počty, výchozí filtr |
| `src/UI/Components/Grids/FilterChip.php` | jeden čip pruhu |
| `src/UI/Components/Grids/DataGridColumnFilters.latte` | lišta, pruh, hlavička s panely, pole podle typu, prázdný výsledek |
| `assets/js/columnFilters.js` | panely (Popper `fixed`), Použít, Zrušit filtr, vrácení hodnot při zavření, období, fokus |
| `assets/scss/_datagridColumnFilters.scss` | vzhled, proměnné `--fa-filters-*` pro světlý i tmavý režim |
| `src/lang/fcadmin.*.yml` | `fcadmin.datagrid.columnFilters.*` |

## Chování

- **Kde je pole filtru.** Každé pole je v DOM jen jednou, formulář tak nemá duplicitní `name`:
  - v hlavičce viditelného sloupce se stejným klíčem (nebo sloupce, nad kterým filtr hledá, `OneColumnFilter::getColumn()`);
  - u aktivního filtru skrytého sloupce v jeho čipu, s upozorněním a odkazem „Zobrazit sloupec“;
  - neaktivní filtr bez sloupce v gridu v nabídce **Další filtry** v liště;
  - neaktivní filtr skrytého sloupce nikde, nastaví se po zobrazení sloupce.
- **Kdy se filtruje.** Až tlačítkem **Použít** (nebo Enter v textovém poli). Trait vypíná autosubmit knihovny.
  Zavření panelu bez Použít (Esc, klik mimo) vrátí pole na hodnoty ze serveru.
- **Zrušit filtr** v panelu vyprázdní jeho pole a odešle formulář, křížek u čipu volá `resetColumnFilter!`,
  „Zrušit filtry“ v pruhu `resetFilter!` (vrátí i výchozí filtr).
- **Počet** „X z Y“ je jen v pruhu a pruh je jen s aktivním filtrem sloupce. Bez filtru se počet nezobrazuje.
- **Výchozí filtr** gridu (`setDefaultFilter()`) ve výchozí hodnotě se za aktivní nepočítá a čip nemá. Jiná hodnota
  je běžný čip, jehož křížek vrátí výchozí hodnotu, ne prázdný filtr.
- **Přepínač výchozího filtru** (`addDefaultFilterToggle()`) je v liště, zapnutý = výchozí hodnota. Vypnutí
  nastaví „Vše“ (`DEFAULT_FILTER_ALL`, `all` - prázdná hodnota by z URL zmizela a grid si stav jinde nepamatuje,
  takže by se po načtení vrátil výchozí filtr) signálem `toggleDefaultFilter!`. Trait přidá volbu „Vše“ do voleb
  filtru a dotazu ji předá jako žádnou hodnotu. Dokud platí výchozí hodnota, řídí se viditelnost sloupce filtru
  nastavením sloupců (typicky `setDefaultHide()`); jiná hodnota sloupec zobrazí vždy a sloupec, který vypnutím
  přibyl, se krátce podbarví (`data-fa-filters-revealed`, `.fa-filters-col-new`). Panel sloupce ukazuje volby
  jako tlačítka s poznámkou „výchozí“ a místo Zrušit filtr má Vrátit výchozí.
- **Telefon** (pod 576 px): lišta má řádky hledání + ⋯ / přepínač / hromadné akce, tlačítka lišty (nastavení
  sloupců, exporty) jsou v nabídce ⋯. Panely jsou spodní list přes celou šířku se ztmavením; po dobu otevření se
  přesunou na konec formuláře gridu, jinak by je přerostly vrstvy lepivé hlavičky tabulky.
- **Fokus po překreslení:** po Použít na hlavičku, po přepínači na přepínač, po odebrání čipu na další čip
  (bez něj na Zrušit filtry, bez pruhu do lišty).
- **Hlavička aktivní filtr neznačí**, je vidět jen v pruhu. Čtečka ho přečte ze skrytého textu v hlavičce.
- **Pole podle typu filtru:** text (Obsahuje), výběr a výběr více hodnot (Tom Select, nabídka je v toku panelu),
  výběr s volbami jen 1 a 0 jako přepínač Vše / Ano / Ne, datum, období (rychlé volby + flatpickr `range`
  vložený do panelu, hodnoty do skrytých polí od/do filtru knihovny), rozsah čísel a zaškrtávátko.
- **Prázdný výsledek** s aktivním filtrem nabídne „Zrušit všechny filtry“.
- Hledání (`search`), přepínač aktivity, období (`FilterPeriod`), přepínače a exporty zůstávají v liště.
  Pokročilé hledání (`advancedSearch`) trait nevykresluje.
