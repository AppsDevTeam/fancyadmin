# Tlačítko Filtry nad gridem

Řádek filtrů nad tabulkou je sbalený pod tlačítkem **Filtry**, dokud uživatel nefiltruje. Stránka s gridem
tak začíná tabulkou, ne formulářem s deseti poli.

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

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Grids/CollapsibleFiltersTrait.php` | šablona gridu, `hasCollapsibleFilters()`, `isFilterActive()` bez výchozího filtru |
| `src/UI/Components/Grids/DataGridFilters.latte` | tlačítko (`filters-toggle`) vedle pole Hledat (`filter-search`), bez hledání v liště nad filtry (`outer-filters`) |
| `assets/js/datagrid.js` | `initCollapsedFilters()` - sbalení řádku bez hledání při načtení a po překreslení snippetu |
| `assets/scss/_datagrid.scss` | `.grid-filters-toggle`, `.grid-filters-bar` (na mobilu tlačítko přes celou šířku) |
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
- **Bez pole Hledat** knihovna kreslí řádek vždy rozbalený (`collapse show`). Lišta `.grid-filters-bar` s tlačítkem
  nese stav ze serveru (`.grid-filters-bar--collapsed`): do načtení JS řádek schová CSS, `initCollapsedFilters()`
  pak odebere `show` a dál řídí Bootstrap collapse. Po překreslení gridu (filtrování, řazení, stránkování) se
  stav nastaví znovu podle serveru - rozbalené zůstanou jen aktivní filtry.
