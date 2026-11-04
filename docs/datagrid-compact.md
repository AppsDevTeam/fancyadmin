# Malý grid v panelu a menu akcí

## Malý grid (`datagrid-compact`)

Grid uvnitř panelu nebo karty, kde je na stránce víc seznamů vedle sebe (např. Pokladna
a Zprávy na kartě pacienta). Celostránkové gridy zůstávají u „Načíst další“.

- hledání je vždy vidět: bílé pole s lupou vlevo, nejvýš 280 px, filtruje během psaní
  (autosubmit datagridu, 200 ms), nativní křížek pole hledání zruší;
- vpravo od hledání dvě šipky (`btn btn-icon btn-sm btn-ghost`), bez čísel stránek
  a bez výběru počtu řádků; na první stránce je zakázaná levá, na poslední pravá,
  šipky jsou vidět i u jediné stránky, aby lišta neskákala;
- prázdný výsledek je jeden řádek „Žádné položky nenalezeny.“, hlavička zůstává;
- tabulka má výchozí vzhled gridu, jen zaoblení `--bs-border-radius-sm` jako malé pole
  hledání nad ní, text se v buňkách zalamuje a první sloupec se nelepí.

Zapnutí v potomkovi `ADT\Datagrid\Component\DataGrid`:

```php
class PanelDataGrid extends DataGrid
{
	use \ADT\FancyAdmin\UI\Components\Grids\CompactDataGridTrait;
}
```

V `initGrid()` gridu:

```php
$grid->setPagination(true);
$grid->setDefaultPerPage(3); // jediný počet řádků, výchozí 5
$grid->setCompactPagerTitles('Novější', 'Starší'); // výchozí Předchozí / Další
$grid->addFilterText('search', '', ['number', 'issuedAt']);
```

Nadpis panelu a akce („Nový doklad“) patří nad grid do šablony projektu, grid kreslí
jen lištu s hledáním a šipkami a tabulku.

**Statická tabulka v panelu** (bez hledání a stránkování, např. výpis formulářů) vypadá
stejně, když dostane kostru gridu přímo v šabloně, bez třídy `table`:

```html
<div class="datagrid datagrid-compact"><div class="table-responsive">
	<table>
		<thead>
			<tr><th>Formulář</th><th>Stav</th><th class="col-action"></th></tr>
			<tr><th class="table-header-divider" colspan="3"><div></div></th></tr>
		</thead>
		<tbody>
			<tr><td>…</td><td>…</td><td class="col-action"><div><a href="#" title="…"><i class="fa-regular fa-envelope"></i></a></div></td></tr>
		</tbody>
	</table>
</div></div>
```

Akce v řádku jsou jen ikony ve `td.col-action > div`; `button` dostane třídu `btn`, aby
převzal vzhled odkazu. Pevné rozvržení sloupců má jen grid se šipkami, statická tabulka
si šířky určí podle obsahu.

| soubor | co dělá |
|---|---|
| `src/UI/Components/Grids/CompactDataGridTrait.php` | šablona, třída `datagrid-compact`, jeden počet řádků, popisky šipek |
| `src/UI/Components/Grids/DataGridCompact.latte` | lišta nad tabulkou (snippet `pagination`), prázdný stav, bez patičky |
| `assets/scss/_datagridCompact.scss` | rozvržení lišty, šipky, zalamování buněk |
| `assets/js/datagrid/datagrid.js` | odeslání po smazání hledání křížkem (událost `search`) |

## Menu akcí (`dropdown-menu-actions`)

Rozbalovací menu tlačítka v hlavičce (Kontakt ▾, Další ▾). Položka má ikonu (16 px,
`--fa-text-muted`), text a případně doplňující údaj vpravo (12 px, tlumený); skupiny
odděluje `.dropdown-divider`, který výchozí menu schovává.

```html
<div class="dropdown">
	<button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown" aria-expanded="false">
		<i class="fa-regular fa-phone"></i>Kontakt<i class="fa-regular fa-chevron-down"></i>
	</button>
	<div class="dropdown-menu dropdown-menu-end dropdown-menu-actions">
		<a class="dropdown-item" href="#">
			<i class="fa-regular fa-phone"></i><span class="dropdown-item-label">Zavolat</span><span class="dropdown-item-meta">+420 735 193 074</span>
		</a>
		<div class="dropdown-divider"></div>
		<a class="dropdown-item" href="#"><i class="fa-regular fa-link"></i><span class="dropdown-item-label">Poslat odkaz</span></a>
	</div>
</div>
```

Mezeru mezi ikonou a textem tlačítka dává `.btn` (`gap`), `me-2` nepřidávat. Styly jsou
v `assets/scss/_dropdownActions.scss`.
