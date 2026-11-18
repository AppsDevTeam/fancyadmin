# Dashboard

Přehled administrace jako mřížka widgetů. Rozložení je v kódu projektu (presenter), ne v konfiguraci
ani v databázi; data každého widgetu dodává `callable`, které se zavolá až při vykreslení a jen jednou.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Controls/Dashboard/DashboardControl.php` | control - mřížka, přidávání widgetů, pořadí, šířka |
| `src/UI/Components/Controls/Dashboard/DashboardControl.latte` | markup mřížky a widgetů |
| `src/UI/Components/Controls/Dashboard/*Widget.php` | widgety `stat`, `tiles`, `table`, `template` (data a nastavení) |
| `src/UI/Components/Controls/Dashboard/DashboardWidgetControl.php` + `.latte` | vykreslení jednoho widgetu - podkomponenta dashboardu se snippetem `body` a persistentním `period` |
| `src/UI/Components/Controls/Dashboard/DashboardPeriod.php` | období (dnes ... tento rok), `getRange()`, `getPreviousRange()` |
| `assets/js/dashboard.js` | výběr období bez načtení stránky (Naja + `history.replaceState`) |
| `src/UI/Presenters/Dashboard.php` | trait presenteru - komponenta `dashboard` z továrny `DashboardControlFactory` (registruje ji rozšíření) |
| `assets/scss/_dashboard.scss` | vzhled (součást `app.scss`), prefix `.fa-dashboard-*`, světlý i tmavý režim |
| `src/lang/fcadmin.*.yml` | `fcadmin.dashboard.empty` (prázdná tabulka), `fcadmin.dashboard.period.*` (období, popisek, tlačítko bez JS) |

Starší třídy `.dashboard-card` v `_theme.scss` s dashboardem nesouvisí a zůstávají.

JS si projekt importuje sám (`import '@fancyadmin/js/dashboard';`). Bez importu by změna selectu v prohlížeči
se zapnutým JS nic neodeslala - tlačítko v `<noscript>` se ukáže jen s vypnutým JS.

## Použití

```php
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControl;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardPeriod;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTableColumn;
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardTile;
use ADT\FancyAdmin\UI\Presenters\Dashboard;

class HomepagePresenter extends BasePresenter
{
	use Dashboard;

	protected function configureDashboard(DashboardControl $dashboard): void
	{
		$dashboard->setColumns(3);

		$dashboard->addStat('pending', 'Čeká na odeslání', fn() => $this->orders->countPending())
			->setNote('objednávek')
			->setIcon('truck')
			->setLink($this->link('Order:default'));

		$dashboard->addTable('sales', 'Prodeje', [
			'label' => 'Ukazatel',
			'value' => new DashboardTableColumn('Hodnota', numeric: true),
		], fn(DashboardPeriod $period) => $this->stats->getSalesRows(...$period->getRange()))
			->setPeriods(DashboardPeriod::THIS_MONTH)
			->setSpan(2);

		$dashboard->addTiles('kpi', fn() => [
			new DashboardTile('Katalog', $this->stats->getCatalogCount(), icon: 'box'),
		])->setSpan(3);

		$dashboard->addTemplate('export', 'Export', __DIR__ . '/export.latte');
	}
}
```

```latte
{block content}
	{control dashboard}
{/block}
```

## Mřížka

- `setColumns(1-6)`, výchozí 3. Pod zlomem `lg` má mřížka nejvýš 2 sloupce, pod `md` jeden.
- `setSpan(n)` - kolik sloupců widget zabere; víc, než má mřížka, se ořízne (pod `lg` na 2).
- `setPosition(n)` - pořadí; widgety se stejnou pozicí (výchozí 0) zůstanou v pořadí přidání.
- `setTitle()`, `setDescription()` - hlavička widgetu. Jméno widgetu (`data-dashboard-widget`) je zároveň jméno
  podkomponenty, smí tedy obsahovat jen písmena, číslice a `_` a musí být na dashboardu jedinečné.

## Widgety

| metoda | widget |
|---|---|
| `addStat($name, $title, fn() => int\|float\|string)` | velké číslo; `setNote()` (text nebo callable), `setIcon()`, `setLink()` (celá karta je odkaz), `setTone()` |
| `addTiles($name, fn() => DashboardTile[], ?$title)` | řada dlaždic bez společné karty (KPI); `DashboardTile`: label, value, note, icon, link, tone |
| `addTable($name, $title, $columns, fn() => iterable<array>)` | tabulka; sloupec je popisek nebo `DashboardTableColumn($label, numeric: true)` (formát a zarovnání doprava), hodnota `Nette\Utils\Html` se neescapuje; prázdná tabulka ukáže `fcadmin.dashboard.empty` |
| `addTemplate($name, ?$title, $file, array\|Closure $parameters)` | vlastní šablona v kartě; dostane `$presenter`, `$dashboard`, `$widget`, `$period` a parametry. Komponentu presenteru vykreslí `{control $presenter['exportForm']}` |

Poznámka statu a parametry šablony mohou být text / pole, nebo `Closure` (ne obecné `callable` - text `'date'` by byl funkce).

- **Čísla** se vypisují česky: mezera (nezlomitelná) mezi tisíci, desetinná čárka, desetinné číslo na 2 místa.
  Text projde beze změny - když má projekt vlastní formát (měna, procenta), vrátí rovnou text.
- **Ikona** je jméno Font Awesome (`truck` → `fa-solid fa-truck`), plné třídy (`fa-regular fa-box`) zůstanou.
- **Tón** (`DashboardTone`): `Neutral`, `Primary`, `Success`, `Warning`, `Danger`, `Info` - barva ikony.

## Období

`setPeriods(DashboardPeriod $default, ?array $options = null)` dá widgetu v hlavičce select období (výchozí
nabídka všech osmi). Data widgetu (callable hodnoty, dlaždic, řádků, parametry šablony, poznámka) pak dostanou
zvolené `DashboardPeriod` jako parametr; widget bez období dostane `null`.

- **Stav je v URL**: persistentní parametr podkomponenty `period` (`?dashboard-sales-period=lastMonth`), takže
  obnovení stránky i odkaz drží zvolená období všech widgetů. Výchozí období do adresy nejde, neznámé nebo
  nenabízené se tiše nahradí výchozím.
- **Přepnutí bez načtení stránky** (`dashboard.js`): změna selectu pošle Najou GET na adresu s novým obdobím
  a `dashboard-sales-redraw=1`, `loadState()` widgetu při AJAXu zneplatní jen jeho snippet `body`; po odpovědi
  `history.replaceState` zapíše adresu bez `redraw`. Hlavička se selectem se nepřekresluje.
- **Proč podkomponenta na widget**: dynamické snippety ve `{snippetArea}` Nette při překreslení oblasti pošle
  všechny (`SnippetRuntime::enter()`), takže by se načetla data všech widgetů. Zneplatněná podkomponenta se
  překreslí sama (`renderChildren()`) a data ostatních se nevolají.
- **Bez JS**: select je v GET formuláři s tlačítkem v `<noscript>`; skrytá pole nesou ostatní parametry
  aktuální adresy (GET formulář query z `action` zahodí).

`DashboardPeriod`:

| | `getRange()` (konec výlučný) | `getPreviousRange()` |
|---|---|---|
| `TODAY`, `YESTERDAY`, `LAST_7_DAYS` | den, den, 7 dní do dneška | stejně dlouhé těsně před |
| `THIS_WEEK` | od pondělí do dneška | stejné dny minulého týdne |
| `THIS_MONTH` | od 1. do dneška | 1. až N. den minulého měsíce (zkráceno na jeho konec) |
| `LAST_MONTH` | celý minulý měsíc | celý měsíc před ním |
| `THIS_QUARTER`, `THIS_YEAR` | od začátku do dneška | stejně dlouhý úsek od začátku předchozího čtvrtletí / roku |
