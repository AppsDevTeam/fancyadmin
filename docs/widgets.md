# Widgety

Mřížka widgetů (`WidgetGridControl`) - čísla, dlaždice, tabulky, grafy a vlastní šablony. Je to obyčejná
komponenta: patří kamkoli do administrace (přehled, detail záznamu, panel), pod libovolným jménem a ve víc
instancích na jedné stránce. Rozložení je v kódu projektu, ne v konfiguraci ani v databázi; data každého
widgetu dodává `callable`, které se zavolá až při vykreslení a jen jednou.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Controls/Widgets/WidgetGridControl.php` | control - mřížka, přidávání widgetů, pořadí, šířka |
| `src/UI/Components/Controls/Widgets/WidgetGridControl.latte` | markup mřížky a widgetů |
| `src/UI/Components/Controls/Widgets/Widget.php` | společný předek widgetů - titulek, popis, šířka, pořadí, období |
| `src/UI/Components/Controls/Widgets/*Widget.php` | widgety `stat`, `kpi`, `figures`, `alerts`, `tiles`, `table`, `chart`, `template` (data a nastavení) |
| `src/UI/Components/Controls/Widgets/WidgetTile.php`, `WidgetTableColumn.php`, `WidgetTone.php`, `WidgetBadge.php`, `WidgetFigure.php`, `WidgetAlert.php` | dlaždice, sloupec tabulky, barva, štítek, údaj a upozornění (hodnotové objekty pro widgety) |
| `src/UI/Components/Controls/Widgets/WidgetFormat.php` | formát čísel a ikon pro šablony |
| `src/UI/Components/Controls/Widgets/WidgetGridControlFactory.php` | generovaná továrna controlu, registruje ji rozšíření |
| `src/UI/Components/Controls/Widgets/WidgetControl.php` + `.latte` | vykreslení jednoho widgetu - podkomponenta mřížky se snippetem `body` a persistentním `period` |
| `src/UI/Components/Controls/Widgets/WidgetPeriod.php` | období (dnes ... tento rok), `getRange()`, `getPreviousRange()` |
| `assets/js/widgets.js` | výběr období bez načtení stránky (Naja + `history.replaceState`), registrace pluginu grafů, aktualizace grafu na místě |
| `assets/js/widgetsTheme.js` | plugin Chart.js `fancyadminTheme` - barvy z CSS proměnných, změna tématu |
| `src/UI/Components/Controls/Widgets/WidgetChart.php` | data grafu widgetu `chart` |
| `src/UI/Presenters/Dashboard.php` | trait přehledu - komponenta `dashboard` z továrny `WidgetGridControlFactory`, rozložení v `configureDashboard()` |
| `assets/scss/_widgets.scss` | vzhled (součást `app.scss`), prefix `.fa-widgets-*`, světlý i tmavý režim |
| `src/lang/fcadmin.*.yml` | `fcadmin.widgets.empty` (prázdná tabulka), `fcadmin.widgets.unavailable`, `fcadmin.widgets.alerts.empty`, `fcadmin.widgets.detail`, `fcadmin.widgets.period.*` (období, popisek, tlačítko bez JS) |

Starší třídy `.dashboard-card` v `_theme.scss` s widgety nesouvisí a zůstávají.

JS si projekt importuje sám (`import '@fancyadmin/js/widgets';`). Bez importu by změna selectu v prohlížeči
se zapnutým JS nic neodeslala - tlačítko v `<noscript>` se ukáže jen s vypnutým JS.

## Použití

Mřížka nemá závislosti, vytvoří ji `new WidgetGridControl()` nebo továrna `WidgetGridControlFactory`
(rozšíření ji registruje jako `fancyadmin.widgetGridControlFactory`) v libovolném `createComponent*()`
presenteru nebo jiné komponenty. Widgety jde přidat v továrně i do už připojené mřížky (třeba v `action*()`
podle záznamu): období z URL si widget pamatuje jako text a vyhodnotí ho až při čtení, takže `setPeriods()`
může přijít i po načtení stavu.

### Přehled (trait `Dashboard`)

Trait je tenký obal pro úvodní stránku: komponenta `dashboard`, rozložení skládá `configureDashboard()`.

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

### Kdekoli a víc mřížek na stránce

```php
class CustomerPresenter extends BasePresenter
{
	protected function createComponentOrderStats(): WidgetGridControl
	{
		$widgetGrid = (new WidgetGridControl())->setColumns(2);
		$widgetGrid->addStat('count', 'Objednávky', fn() => $this->orders->countFor($this->customer));
		$widgetGrid->addChart('revenue', 'Útrata', fn(WidgetPeriod $period) => $this->stats->getChart($this->customer, $period))
			->setPeriods(WidgetPeriod::THIS_YEAR);
		return $widgetGrid;
	}

	protected function createComponentReturnStats(): WidgetGridControl
	{
		$widgetGrid = new WidgetGridControl();
		$widgetGrid->addTable('revenue', 'Vratky', ['label' => 'Důvod', 'count' => new WidgetTableColumn('Počet', numeric: true)],
			fn(WidgetPeriod $period) => $this->returns->getReasonRows($this->customer, $period))
			->setPeriods(WidgetPeriod::THIS_YEAR);
		return $widgetGrid;
	}
}
```

```latte
{control orderStats}
…
{control returnStats}
```

Všechno, co widget nese v URL a v DOM, je odvozené od cesty komponenty (`getUniqueId()`), takže se dvě mřížky
neperou ani se stejnými jmény widgetů: období `orderStats-revenue-period` a `returnStats-revenue-period`,
snippety `snippet-orderStats-revenue-body` a `snippet-returnStats-revenue-body`, překreslení
`orderStats-revenue-redraw`. Mřížka vnořená v jiné komponentě má celou cestu (`panel-stats-revenue-period`).
Formulář období každého widgetu nese ve skrytých polích období všech ostatních widgetů stránky, přepnutí bez JS
tedy nic nezahodí. Šablona mřížky dostane proměnnou `$widgetGrid`.
## Mřížka

- `setColumns(1-12)`, výchozí 3. Pod zlomem `lg` má mřížka nejvýš 2 sloupce, pod `md` jeden.
- `setSpan(n)` - kolik sloupců widget zabere; víc, než má mřížka, se ořízne (pod `lg` na 2).
- `setPosition(n)` - pořadí; widgety se stejnou pozicí (výchozí 0) zůstanou v pořadí přidání.
- `setTitle()`, `setDescription()` - hlavička widgetu. Popis se nevypisuje pod nadpis, ukáže ho tooltip
  otazníku vpravo v hlavičce (`.fa-widget-hint`, `docs/tooltip.md`); karty `kpi`, `figures` a `alerts`
  ho mají místo otazníku na nadpisu (najetí myší, fokus z klávesnice). Na dotyku se neukáže, patří do něj
  jen doplňující vysvětlení, ne nic, bez čeho se widget nedá číst. U karty s proklikem je nadpis nad
  odkazem - klik na něj kartu neotevře, jen ukáže nápovědu. Jméno widgetu (`data-widget`) je zároveň jméno
  podkomponenty, smí tedy obsahovat jen písmena, číslice a `_` a musí být v mřížce jedinečné (v jiné mřížce
  stejné jméno být smí).

- `setSubtitle()` - šedý dovětek za nadpisem (období, „stav k …“), text nebo `Closure` s obdobím.
- `setBadges()` - štítky `WidgetBadge($text, WidgetTone, ?$title)` v hlavičce (u `kpi` pod hodnotou), pole nebo `Closure`.
- `setLink($url, ?$label)` - bez popisku je odkazem celá karta (šipka vpravo v hlavičce), s popiskem odkaz
  v patičce („Všechna upozornění ›“). Místo adresy jde předat `Closure` s obdobím widgetu - proklik pak
  otevře podrobnosti na zvoleném období (`fn(?WidgetPeriod $period) => $this->link('Stats:', [...])`).
- `setUnavailable($reason)` - widget, pro který zatím nejsou data: místo obsahu „Data zatím nejsou k dispozici“
  a důvod, data widgetu se nevolají. Rozložení drží místo i pro ukazatele, které teprve přibudou; karta
  natažená na výšku souseda v řadě má hlášku uprostřed.
- `setCollapsible()` - sbalitelná karta (`<details>`): vidět je nadpis, obsah po kliknutí; sbalitelné widgety
  se otevírají a zavírají každý zvlášť a rozbalený nenatahuje sousedy v řadě.
- `addSwitch($name, [hodnota => popisek], ?$default)` - přepínač v hlavičce (skupina tlačítek vpravo, např.
  jednotka „ks / Kč“ nebo rozsah „12 měsíců / 3 roky / Vše“), víc přepínačů vedle sebe. Data si volbu přečtou
  přes `getSwitch($name)` (closure dostane widget referencí: `$chart = $grid->addChart('trend', 'Vývoj',
  function ($period) use (&$chart) { … $chart->getSwitch('unit') … })->addSwitch(…)`). Volba je v URL
  (`dashboard-trend-switch[unit]=kc`, výchozí se nepíše, neznámá = výchozí); odkazy fungují i bez JS,
  s JS (`widgets.js`) se překreslí jen widget jako u období a tlačítko se označí.

## Hlavička a společné období mřížky

`setHeader($title, $text)` vykreslí nad widgety pás v primární barvě (pozdrav, datum). `setHeaderImage($url)`
do pásu dá obrázek (vpravo, zleva překrytý primární barvou), který dolů měkce přejde do pozadí stránky za
první řadou widgetů. `setPeriods($default,
$options)` na mřížce přidá do pásu výběr období, které dostanou všechny widgety **bez vlastního** výběru
(`Widget::setPeriods()` má přednost). Stav je v URL (`dashboard-period`), výchozí do odkazů nejde. Změna
selectu odešle formulář a stránka se načte znovu (`widgets.js`) - mění se tím skoro všechny widgety.

Podstránka dashboardu (detail, na který se z karty proklikne) má hlavičku `setPage($title, $subtitle,
$breadcrumbs)` + `{control dashboard:page}` (`renderPage()`): drobečková navigace (`['Dashboard' => odkaz, …,
'Aktuální' => null]`, poslední položka bez odkazu), ve stejném řádku vpravo výběr období, pod ní nadpis
a šedý podtitul (text nebo `Closure` s obdobím). Za podtitulem mohou být štítky `setPageBadges([WidgetBadge…])`
(pole nebo `Closure` s obdobím, např. „zatím není tržba“). Přepínač nebo záložky pod hlavičkou dejte do
`<div class="fa-widgets-page-toolbar">` (stejná šířka jako mřížka). Samotný výběr období bez hlavičky:
`{control dashboard:period}` (`renderPeriod()`) ho vykreslí samostatně jako tónované tlačítko a mřížka
vykreslená potom ho už nemá; bez `setHeader()` pak nemá ani pás hlavičky. Stejné jméno komponenty
(`dashboard`) znamená stejný parametr období, odkaz z dashboardu ho tak může předat.

Vzhled mřížky jde projektu nastavit CSS proměnnými (výchozí = vzhled administrace): `--fa-widgets-radius`
(zaoblení karet a pásu) a `--fa-widgets-max-width` (nejširší obsah mřížky a hlavičky podstránky, uprostřed).

## Widgety

| metoda | widget |
|---|---|
| `addStat($name, $title, fn() => int\|float\|string)` | velké číslo; `setNote()` (text nebo callable), `setIcon()`, `setLink()` (celá karta je odkaz), `setTone()` |
| `addKpi($name, $title, fn() => int\|float\|string\|null)` | KPI karta: hodnota, `setUnit()` (text nebo `Closure` - jednotka podle velikosti čísla), `setSparkline(fn() => list<number\|null>)` (křivka 72 × 28 v SVG), `setBadges()` pod hodnotou, `setNote()`, `setTone()` (barva hodnoty a křivky), `setSplit([WidgetSplit…])` (pruh poměru s legendou; s `note` / `extra` řádková legenda, `extra` v barvě tónu nebo `extraTone`); `null` = „–“ |
| `addKpiParts($name, $title, fn() => WidgetFigure[])` | dělená KPI karta - části vedle sebe oddělené linkou (popisek, hodnota, jednotka, štítky, poznámka) |
| `addFigures($name, $title, fn() => WidgetFigure[])` | řada údajů v jedné kartě oddělených linkou; `WidgetFigure`: label, value, unit, note (šedý doplněk), tone, title (bublina popisku), badges |
| `addAlerts($name, $title, fn() => WidgetAlert[])` | upozornění k řešení: text, tón (`Warning` / `Danger` - ikona a podbarvení), poznámka, hodnota, odkaz s popiskem (výchozí `fcadmin.widgets.detail`); prázdné `fcadmin.widgets.alerts.empty`. `setCompact()` = kompaktní seznam ve sloupcích (tečka v barvě tónu místo ikony, bez podbarvení, odkazem je celý řádek se šipkou, popisek odkazu jen jako `title`), `setLimit(n)` = vidět prvních n, zbytek pod „Zobrazit všech N“ (`fcadmin.widgets.alerts.more` / `.less`) |
| `addTiles($name, fn() => WidgetTile[], ?$title)` | řada dlaždic bez společné karty (KPI); `WidgetTile`: label, value, note, icon, link, tone |
| `addTable($name, $title, $columns, fn() => iterable<array>)` | tabulka; sloupec je popisek nebo `WidgetTableColumn($label, numeric: true)` (formát a zarovnání doprava; `wrap: true` zalomí dlouhý text, jinak je řádek jako v gridu na jednom řádku), hodnota `Nette\Utils\Html` se neescapuje; prázdná tabulka ukáže `fcadmin.widgets.empty`. Volitelně `WidgetTableColumn(group: '…')` (nadpis skupiny nad sousedními sloupci), `setTotal(řádek|fn)` (řádek Celkem v patičce) a `setRowLink(fn(array $row): ?string)` (proklik řádku - odkazem je první buňka a celý řádek) |
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

- **Stav je v URL**: persistentní parametr podkomponenty `period` (`?<mřížka>-<widget>-period`, na přehledu
  `?dashboard-sales-period=lastMonth`), takže obnovení stránky i odkaz drží zvolená období všech widgetů.
  Neznámé nebo nenabízené se tiše nahradí výchozím; výchozí ani neplatné do odkazů nejde (`saveState()`).
- **Přepnutí bez načtení stránky** (`widgets.js`): změna selectu pošle Najou GET na adresu s novým obdobím
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
| `LAST_12_MONTHS` | od 1. dne měsíce před 11 měsíci do dneška | stejný úsek o rok dřív |

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
- `highlight: [od, do]` - podbarvené pásmo přes popisky osy x (indexy včetně), např. zvolené období v grafu
  delšího vývoje; kreslí ho plugin `fancyadminTheme` průhlednou primární barvou pod datasety.
- `format` jsou volby `Intl.NumberFormat` pro osu hodnot a tooltip (builtin `Chart`), `options` další volby
  Chart.js (mají přednost před výchozími `maintainAspectRatio: false`, `interaction: index`).
- Šablona vypíše `<canvas data-adt-chart='{…}'>` (JSON explicitně přes `json_encode` - Latte před 3.1 by pole
  v atributu vypsalo jako „Array“) s `plugins: ["fancyadminTheme"]`; prázdné `labels` nebo
  `datasets` ukážou `fcadmin.widgets.empty`.

**Plugin `fancyadminTheme`** (`widgetsTheme.js`, registruje ho `widgets.js` přes `registerChartPlugins()`):

- datasety bez barvy dostanou paletu z proměnných `--primaryColor`, `--fa-text-muted` (druhý dataset - typicky
  předchozí období - je tlumený), `--dashboardAccentColor`, `--bs-info`, `--bs-success`, `--bs-warning`; čára má
  průhlednou výplň, sloupce plnou, koláč barvu na díl;
- mřížka a okraje os `--fa-line`, popisky os `--fa-text-muted`, legenda `--fa-text`, tooltip `--fa-surface` /
  `--fa-text` / `--fa-border`;
- barvu zadanou v konfiguraci nepřepíše - přepisuje jen hodnoty, které sám nastavil;
- na `fancyadmin:theme-change` barvy přepočítá a grafy překreslí (`update('none')`);
- builtin při změně atributu vymění celé volby a Chart.js si volby os vyhodnotí ještě před `beforeUpdate`, proto
  plugin po takové aktualizaci projde graf jednou znovu.

**Přepnutí období grafu**: `widgets.js` při překreslení snippetu widgetu, ve kterém je graf a nový obsah je
zase graf, nevymění HTML, jen přepíše `data-adt-chart` stávajícího canvasu - builtin graf aktualizuje na místě
(bez probliknutí, s animací přechodu). Prázdná data nebo jiný obsah jdou běžnou výměnou.
