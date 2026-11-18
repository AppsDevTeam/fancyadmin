# Tlačítko Filtry a lišta nad gridem

Řádek filtrů nad tabulkou je sbalený pod tlačítkem **Filtry**, dokud uživatel nefiltruje. Stránka s gridem
tak začíná tabulkou, ne formulářem s deseti poli. Tlačítko je v jedné liště s obsahem projektu (hromadné akce,
vlastní tlačítka) a ozubeným kolem sloupců; rozbalené filtry jsou pod ní.

## Použití

Trait do potomka `ADT\Datagrid\Component\DataGrid`:

```php
use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\UI\Components\Grids\CollapsibleFiltersTrait;

class AppDataGrid extends DataGrid
{
	use CollapsibleFiltersTrait;
}
```

Šablona projektu (`getTemplateFile()`, stromový grid fancyadminu) dál dědí z `{extends $originalTemplate}` -
trait vrací jako `$originalTemplate` šablonu `DataGridFilters.latte`, která dědí ze šablony knihovny.

Obsah projektu do lišty přidá šablona gridu projektu blokem `toolbar`:

```latte
{extends $originalTemplate}

{define toolbar}
	<div n:if="$hasGroupActions" class="row-group-actions d-flex align-items-center gap-2">…</div>
{/define}
```

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Grids/CollapsibleFiltersTrait.php` | šablona gridu, `hasCollapsibleFilters()`, `isFilterActive()` bez výchozího filtru |
| `src/UI/Components/Grids/DataGridFilters.latte` | tlačítko (`filters-toggle`), blok projektu `toolbar` a kopie ozubeného kola (`toolbar-settings`) - vedle pole Hledat (`filter-search`), bez hledání v liště nad filtry (`outer-filters`) |
| `assets/js/datagrid.js` | `initCollapsedFilters()` - sbalení řádku bez hledání při načtení a po překreslení snippetu |
| `assets/scss/_datagrid.scss` | `.grid-filters-toggle`, `.datagrid-filters-bar` (jeden řádek, ozubené kolo vpravo; na mobilu Filtry s ozubeným kolem a obsah projektu pod nimi), schování původního ozubeného kola |
| `src/lang/fcadmin.*.yml` | `fcadmin.datagrid.filters` |

## Chování

- **Tlačítko se ukáže**, jen když grid má filtry, které se sbalují - kromě hledání (`search`), aktivity
  (`isActive`), pokročilého hledání (`advancedSearch`), přepínačů a období, ty zůstávají v liště nad gridem.
- **Sbalené ve výchozím stavu, rozbalené při aktivním filtru.** Výchozí filtr gridu (`setDefaultFilter()`) se za
  aktivní nepočítá a prázdné hodnoty, které formulář filtru posílá i nevyplněné, také ne. Jinak by grid
  s výchozím stavem měl filtry pořád rozbalené.
- **Sbalení filtr zruší** - `datagrid.js` po `hidden.bs.collapse` klikne na „Resetovat filtr“ (stejné jako dosud).
- **S polem Hledat** sbaluje řádek knihovna (`grid-filters-{grid} collapse`, `show` jen při aktivním filtru)
  a tlačítko stojí vedle hledání.
- **Bez pole Hledat** knihovna kreslí řádek vždy rozbalený (`collapse show`). Lišta `.datagrid-filters-bar` s tlačítkem
  nese stav ze serveru (`.datagrid-filters-bar--collapsed`): do načtení JS řádek schová CSS, `initCollapsedFilters()`
  pak odebere `show` a dál řídí Bootstrap collapse. Po překreslení gridu (filtrování, řazení, stránkování) se
  stav nastaví znovu podle serveru - rozbalené zůstanou jen aktivní filtry.
- **Lišta** se vykreslí, když má obsah (tlačítko, blok `toolbar` nebo ozubené kolo), jinak vůbec. Pořadí: Filtry,
  obsah projektu, ozubené kolo vpravo. Na telefonu (pod `sm`) jsou Filtry a ozubené kolo v prvním řádku a obsah
  projektu přes celou šířku pod nimi.
- **Ozubené kolo** (`canHideColumns()`) kreslí šablona knihovny mimo jakýkoli blok, až pod řádkem filtrů. Lišta má
  jeho kopii (`.datagrid-toolbar-settings`, stejné odkazy `hideColumn!` / `showColumn!`) a původní `.datagrid-settings`
  schová pravidlo `form:has(.datagrid-toolbar-settings) > .datagrid-settings`. Při povýšení knihovny kopii porovnat.
