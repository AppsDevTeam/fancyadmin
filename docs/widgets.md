# Dashboard

Přehled administrace jako mřížka widgetů. Rozložení je v kódu projektu (presenter), ne v konfiguraci
ani v databázi; data každého widgetu dodává `callable`, které se zavolá až při vykreslení a jen jednou.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Controls/Widgets/WidgetGridControl.php` | control - mřížka, přidávání widgetů, pořadí, šířka |
| `src/UI/Components/Controls/Widgets/WidgetGridControl.latte` | markup mřížky a widgetů |
| `src/UI/Components/Controls/Widgets/Widget.php` | společný předek widgetů - titulek, popis, šířka, pořadí, období |
| `src/UI/Components/Controls/Widgets/*Widget.php` | widgety `stat`, `tiles`, `table`, `chart`, `template` (data a nastavení) |
| `src/UI/Components/Controls/Widgets/WidgetTile.php`, `WidgetTableColumn.php`, `WidgetTone.php` | dlaždice, sloupec tabulky a barva ikony (hodnotové objekty pro widgety) |
| `src/UI/Components/Controls/Widgets/WidgetFormat.php` | formát čísel a ikon pro šablony |
| `src/UI/Components/Controls/Widgets/WidgetGridControlFactory.php` | generovaná továrna controlu, registruje ji rozšíření |
| `src/UI/Components/Controls/Widgets/WidgetControl.php` + `.latte` | vykreslení jednoho widgetu - podkomponenta dashboardu se snippetem `body` a persistentním `period` |
| `src/UI/Components/Controls/Widgets/WidgetPeriod.php` | období (dnes ... tento rok), `getRange()`, `getPreviousRange()` |
| `assets/js/widgets.js` | výběr období bez načtení stránky (Naja + `history.replaceState`), registrace pluginu grafů, aktualizace grafu na místě |
| `assets/js/widgetsTheme.js` | plugin Chart.js `fancyadminTheme` - barvy z CSS proměnných, změna tématu |
| `src/UI/Components/Controls/Widgets/WidgetChart.php` | data grafu widgetu `chart` |
| `src/UI/Presenters/Dashboard.php` | trait presenteru - komponenta `dashboard` z továrny `WidgetGridControlFactory` (registruje ji rozšíření) |
| `assets/scss/_widgets.scss` | vzhled (součást `app.scss`), prefix `.fa-widgets-*`, světlý i tmavý režim |
| `src/lang/fcadmin.*.yml` | `fcadmin.widgets.empty` (prázdná tabulka), `fcadmin.widgets.period.*` (období, popisek, tlačítko bez JS) |

Starší třídy `.dashboard-card` v `_theme.scss` s dashboardem nesouvisí a zůstávají.

JS si projekt importuje sám (`import '@fancyadmin/js/widgets';`). Bez importu by změna selectu v prohlížeči
se zapnutým JS nic neodeslala - tlačítko v `<noscript>` se ukáže jen s vypnutým JS.

## Použití

```php
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetGridControl;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetPeriod;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetTableColumn;
use ADT\FancyAdmin\UI\Components\Controls\Widgets\WidgetTile;
use ADT\FancyAdmin\UI\Presenters\Dashboard;

class HomepagePresenter extends BasePresenter
{
	use Dashboard;

	protected function configureDashboard(WidgetGridControl $widgetGrid): void
	{
		$widgetGrid->setColumns(3);

		$widgetGrid->addStat('pending', 'Čeká na odeslání', fn() => $this->orders->countPending())
			->setNote('objednávek')
			->setIcon('truck')
			->setLink($this->link('Order:default'));

		$widgetGrid->addTable('sales', 'Prodeje', [
			'label' => 'Ukazatel',
			'value' => new WidgetTableColumn('Hodnota', numeric: true),
		], fn(WidgetPeriod $period) => $this->stats->getSalesRows(...$period->getRange()))
			->setPeriods(WidgetPeriod::THIS_MONTH)
			->setSpan(2);

		$widgetGrid->addTiles('kpi', fn() => [
			new WidgetTile('Katalog', $this->stats->getCatalogCount(), icon: 'box'),
		])->setSpan(3);

		$widgetGrid->addTemplate('export', 'Export', __DIR__ . '/export.latte');
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
- `setTitle()`, `setDescription()` - hlavička widgetu. Jméno widgetu (`data-widget`) je zároveň jméno
  podkomponenty, smí tedy obsahovat jen písmena, číslice a `_` a musí být na dashboardu jedinečné.

## Widgety

| metoda | widget |
|---|---|
| `addStat($name, $title, fn() => int\|float\|string)` | velké číslo; `setNote()` (text nebo callable), `setIcon()`, `setLink()` (celá karta je odkaz), `setTone()` |
| `addTiles($name, fn() => WidgetTile[], ?$title)` | řada dlaždic bez společné karty (KPI); `WidgetTile`: label, value, note, icon, link, tone |
| `addTable($name, $title, $columns, fn() => iterable<array>)` | tabulka; sloupec je popisek nebo `WidgetTableColumn($label, numeric: true)` (formát a zarovnání doprava), hodnota `Nette\Utils\Html` se neescapuje; prázdná tabulka ukáže `fcadmin.widgets.empty` |
| `addChart($name, $title, fn() => WidgetChart)` | graf Chart.js přes builtin `Chart` z adt-js-components, viz *Grafy* |
| `addTemplate($name, ?$title, $file, array\|Closure $parameters)` | vlastní šablona v kartě; dostane `$presenter`, `$widgetGrid`, `$widget`, `$period` a parametry. Komponentu presenteru vykreslí `{control $presenter['exportForm']}` |

Poznámka statu a parametry šablony mohou být text / pole, nebo `Closure` (ne obecné `callable` - text `'date'` by byl funkce).

- **Čísla** se vypisují česky: mezera (nezlomitelná) mezi tisíci, desetinná čárka, desetinné číslo na 2 místa.
  Text projde beze změny - když má projekt vlastní formát (měna, procenta), vrátí rovnou text.
- **Ikona** je jméno Font Awesome (`truck` → `fa-solid fa-truck`), plné třídy (`fa-regular fa-box`) zůstanou.
- **Tón** (`WidgetTone`): `Neutral`, `Primary`, `Success`, `Warning`, `Danger`, `Info` - barva ikony.

## Období

`setPeriods(WidgetPeriod $default, ?array $options = null)` dá widgetu v hlavičce select období (výchozí
nabídka všech osmi). Data widgetu (callable hodnoty, dlaždic, řádků, parametry šablony, poznámka) pak dostanou
zvolené `WidgetPeriod` jako parametr; widget bez období dostane `null`.

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

`WidgetPeriod`:

| | `getRange()` (konec výlučný) | `getPreviousRange()` |
|---|---|---|
| `TODAY`, `YESTERDAY`, `LAST_7_DAYS` | den, den, 7 dní do dneška | stejně dlouhé těsně před |
| `THIS_WEEK` | od pondělí do dneška | stejné dny minulého týdne |
| `THIS_MONTH` | od 1. do dneška | 1. až N. den minulého měsíce (zkráceno na jeho konec) |
| `LAST_MONTH` | celý minulý měsíc | celý měsíc před ním |
| `THIS_QUARTER`, `THIS_YEAR` | od začátku do dneška | stejně dlouhý úsek od začátku předchozího čtvrtletí / roku |

## Grafy

`addChart()` vrací z callable `WidgetChart`:

```php
$widgetGrid->addChart('revenue', 'Tržba po dnech', fn(WidgetPeriod $period) => new WidgetChart(
	'line',
	$labels,                                         // popisky osy
	[
		['label' => 'Toto období', 'data' => $current],
		['label' => 'Předchozí období', 'data' => $previous],
	],
	format: ['style' => 'currency', 'currency' => 'CZK', 'maximumFractionDigits' => 0],
))->setPeriods(WidgetPeriod::THIS_MONTH)->setSpan(2);
```

- `type`: `line`, `bar`, `doughnut`, `pie`, jiný vyhodí výjimku; `indexAxis: 'y'` = vodorovné sloupce (vyšší
  plocha, např. top 10).
- `labels`: popisek je text, nebo pole textů = víc řádků (dlouhý název produktu u vodorovných sloupců).
- `datasets`: každý musí mít pole `data`, další klíče jsou volby datasetu Chart.js (vlastní barva, `type`).
- `format` jsou volby `Intl.NumberFormat` pro osu hodnot a tooltip (builtin `Chart`), `options` další volby
  Chart.js (mají přednost před výchozími `maintainAspectRatio: false`, `interaction: index`).
- Šablona vypíše `<canvas data-adt-chart='{…}'>` s `plugins: ["fancyadminTheme"]`; prázdné `labels` nebo
  `datasets` ukážou `fcadmin.widgets.empty`.

**Plugin `fancyadminTheme`** (`widgetsTheme.js`, registruje ho `dashboard.js` přes `registerChartPlugins()`):

- datasety bez barvy dostanou paletu z proměnných `--primaryColor`, `--fa-text-muted` (druhý dataset - typicky
  předchozí období - je tlumený), `--dashboardAccentColor`, `--bs-info`, `--bs-success`, `--bs-warning`; čára má
  průhlednou výplň, sloupce plnou, koláč barvu na díl;
- mřížka a okraje os `--fa-line`, popisky os `--fa-text-muted`, legenda `--fa-text`, tooltip `--fa-surface` /
  `--fa-text` / `--fa-border`;
- barvu zadanou v konfiguraci nepřepíše - přepisuje jen hodnoty, které sám nastavil;
- na `fancyadmin:theme-change` barvy přepočítá a grafy překreslí (`update('none')`);
- builtin při změně atributu vymění celé volby a Chart.js si volby os vyhodnotí ještě před `beforeUpdate`, proto
  plugin po takové aktualizaci projde graf jednou znovu.

**Přepnutí období grafu**: `dashboard.js` při překreslení snippetu widgetu, ve kterém je graf a nový obsah je
zase graf, nevymění HTML, jen přepíše `data-adt-chart` stávajícího canvasu - builtin graf aktualizuje na místě
(bez probliknutí, s animací přechodu). Prázdná data nebo jiný obsah jdou běžnou výměnou.
