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

Nastavení sloupců (viditelnost, pořadí, šířka z dialogu Sloupce) se bez dalšího drží v session. Uložené sestavy
(filtry + nastavení sloupců pod názvem, soukromé nebo sdílené s týmem) bez úložiště nejsou. Obojí zapne konfigurace,
`BaseGridTrait` pak úložiště každému gridu s `ColumnFiltersTrait` nastaví sám pod klíčem
`{presenter}:{plné jméno gridu}`:

```neon
fancyadmin:
	datagrid:
		presetEntity: App\Model\Entities\GridFilter              # implements GridFilter, use GridFilterTrait
		columnSettingEntity: App\Model\Entities\GridColumnSetting # implements GridColumnSetting, use GridColumnSettingTrait
```

- **Zrušení sestavy** (křížek na štítku) vrátí sloupce a filtry, které uživatel měl před první vybranou sestavou -
  záloha je v nastavení sloupců pod klíčem `beforePreset`, přepnutí na další sestavu ji nemění.
- **Sestavy** leží v tabulce uložených filtrů (`grid_filter`) vedle pokročilých filtrů datagrid-components, odliší je
  sloupec `type` (`GridFilterType::Preset` / `Advanced`). `GridFilterQueryTrait` bez `byType()` vrací jen pokročilé,
  jinak by je pokročilé hledání vypsalo mezi své. Autor je `createdBy`, sdílenou (`shared`) vidí celý tým, měnit
  a mazat ji smí autor a uživatel s právem na plná data (`isAllowedFullDataAclResource()`); v projektu s účty patří
  sestava k vybranému účtu. Unikátní index si entita určí sama - název je unikátní u autora, třeba
  `#[ORM\UniqueConstraint(columns: ['grid', 'type', 'created_by_id', 'name'])]`.
- **Nastavení sloupců** je jeden řádek na identitu a grid (`GridColumnSettingTrait`, unikátní `identity` + `grid`,
  identita se smaže i s nastavením).
- **Přechod existujícího projektu**: `GridFilterTrait` přidal sloupce `type` (výchozí `advanced`), `shared` (výchozí
  1 - dosavadní pokročilé filtry jsou společné) a `columns`; vygenerujte migraci. Query objekt uložených filtrů
  může přepisy `byGrid()` / `byName()` kvůli návratovému typu smazat, trait už vrací `static`.
- Jiné úložiště (třeba API) jde nastavit ručně přes `setColumnSettingsStorage()` / `setPresetStorage()`
  (`GridColumnSettingsStorage`, `GridPresetStorage`).

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
| `src/UI/Components/Grids/GridColumnSettingsStorage.php` | úložiště nastavení sloupců u uživatele |
| `src/UI/Components/Grids/GridPresetStorage.php`, `GridPreset.php`, `GridPresetException.php` | uložené sestavy |
| `src/Model/Grid/DoctrineGridPresetStorage.php`, `DoctrineGridColumnSettingsStorage.php` | úložiště v entitách projektu (konfigurace `datagrid`) |
| `src/Model/Entities/GridFilterTrait.php`, `GridColumnSettingTrait.php`, `Enums/GridFilterType.php` | entity sestav a nastavení sloupců |
| `src/UI/Components/Grids/DataGridColumnFilters.latte` | lišta, pruh, hlavička s panely, pole podle typu, prázdný výsledek |
| `assets/js/columnFilters.js` | panely (Popper `fixed`), Použít, Zrušit filtr, vrácení hodnot při zavření, období, fokus |
| `assets/scss/_datagridColumnFilters.scss` | vzhled, proměnné `--fa-filters-*` pro světlý i tmavý režim |
| `src/lang/fcadmin.*.yml` | `fcadmin.datagrid.columnFilters.*` |

## Chování

- **Kde je pole filtru.** Každé pole je v DOM jen jednou, formulář tak nemá duplicitní `name`:
  - v hlavičce viditelného sloupce se stejným klíčem (nebo sloupce, nad kterým filtr hledá, `OneColumnFilter::getColumn()`);
  - u aktivního filtru skrytého sloupce v jeho čipu, s upozorněním a odkazem „Zobrazit sloupec“;
  - neaktivní filtr skrytého sloupce nikde, nastaví se po zobrazení sloupce;
  - filtr bez sloupce v gridu nikde - grid ho mít nemá (pole by nebylo kde otevřít).
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
- **Hledání ve viditelných sloupcích** (`addFilterFulltext()`, filtr `fulltext`): pole na začátku lišty, mimo
  překreslovaný snippet (rozepsaný text i fokus zůstanou). Odešle se 400 ms po posledním znaku od 2 znaků,
  smazání a Enter hned. Hledá přes NEBO ve všech textových filtrech sloupců, které jsou vidět. `BaseGridTrait`
  ho přidá každému gridu s textovým filtrem (bez vlastního `search`): každý filtr pustí na nový query objekt
  gridu (`createQueryObject()` + `initQueryObject()`, vlastní podmínka filtru, jinak `by(sloupce, CONTAINS)`),
  sjednotí nalezená ID a grid omezí `by('id', $ids)` - filtry s vlastní podmínkou přes NEBO jinak spojit nejde.
  Hledání není aktivní filtr: nemá čip ani počet.
- **Uložené sestavy:** štítek vybrané sestavy (persistentní parametr gridu `preset`) a tlačítko seznamu jsou ve
  vyhledávání, Uložit sestavu v liště (na telefonu v nabídce ⋯). Sestava ukládá filtry kromě hledání a nastavení
  sloupců; výběr (`applyPreset!`) nastaví obojí, křížek na štítku (`clearPreset!`) vrátí výchozí filtry. Když se
  filtry nebo sloupce od sestavy liší, štítek ukáže „· upraveno“ a panel uložení nabídne Uložit změny do sestavy
  (POST `overwrite`). Nová sestava `savePreset!` (POST `name`, `shared`), přejmenování a viditelnost
  `updatePreset!` (POST `id`, `name`, `shared`), smazání `deletePreset!`. Pole sestav nemají `name`, do formuláře
  filtrů se neposílají. Kdo smí sestavu měnit, rozhoduje úložiště (`GridPreset::$editable`, jinak
  `GridPresetException` 403); neplatný název vrátí JS v `payload.faFiltersPresetError`.
- **Export** (`setExportPanel($handler, $email)`): tlačítko a panel místo tlačítek exportů knihovny - formát
  XLSX / CSV, „Jen sloupce zobrazené v tabulce“ (výchozí) a „Poslat na e-mail“ s předvyplněnou adresou. Stažení
  otevře JS mimo AJAX na adrese signálu `exportGrid!` (parametry `format`, `visible`), odeslání jde AJAXem
  (POST `format`, `visible`, `email`); neplatnou adresu vrátí `payload.faFiltersExportError`. Data a soubor
  připraví `$handler` vlastníka gridu podle aktuálních filtrů a hledání. Hotové napojení na adt/exporter je
  `addExportPanel()` z `BaseGridTrait`, viz [datagrid-export.md](datagrid-export.md).
- **Sloupce:** tlačítko Sloupce (jen grid s `setColumnsHideable()`) nahrazuje ozubené kolo knihovny a otevře dialog
  se zaškrtávátkem viditelnosti, šířkou Úzký / Auto / Široký (`fa-filters-col-narrow` / `-wide` na buňkách) a
  šipkami pořadí. Uloží se až Použít (signál `saveColumns!`, POST `columns` = JSON). Skryté sloupce knihovny
  (`_grid_hidden_columns`) trait čte a ukládá v tomtéž nastavení, takže `showColumn!` / `hideColumn!` fungují dál.
  Sloupce, které nastavení nezná (přibyly později), jsou na konci. Dialog se po dobu otevření přesune do `body`
  (obsah stránky je pevně umístěný, modal by skončil pod zatmavením) a zavření bez Použít ho vrátí.
- **Telefon** (pod 576 px): lišta má řádky hledání + ⋯ / přepínač / hromadné akce, tlačítka lišty (nastavení
  sloupců, exporty) jsou v nabídce ⋯. Panely jsou spodní list přes celou šířku se ztmavením; po dobu otevření se
  přesunou na konec formuláře gridu, jinak by je přerostly vrstvy lepivé hlavičky tabulky.
- **Fokus po překreslení:** po Použít na hlavičku, po přepínači na přepínač, po odebrání čipu na další čip
  (bez něj na Zrušit filtry, bez pruhu do lišty).
- **Hlavička aktivní filtr neznačí**, je vidět jen v pruhu. Čtečka ho přečte ze skrytého textu v hlavičce.
- **Pole podle typu filtru:** text (Obsahuje), výběr a výběr více hodnot (Tom Select, nabídka je v toku panelu),
  výběr s volbami jen 1 a 0 jako přepínač Vše / Ano / Ne, datum, období (rychlé volby + flatpickr `range`
  vložený do panelu, hodnoty do skrytých polí od/do filtru knihovny), rozsah čísel a zaškrtávátko.
- **Výběr více hodnot se skupinami** (`addFilterMultiSelect()` s volbami ve skupinách = `optgroup`): klik na nadpis
  skupiny v nabídce Tom Selectu vybere celou skupinu, u celé vybrané ji odznačí. Celá vybraná skupina (víc než
  jedna volba) je v čipu jednou položkou „Skupina (vše)“.
- **Prázdný výsledek** s aktivním filtrem nabídne „Zrušit všechny filtry“.
- Hledání (`search`), přepínač aktivity, období (`FilterPeriod`), přepínače a exporty zůstávají v liště.
  Pokročilé hledání (`advancedSearch`) trait nevykresluje.
