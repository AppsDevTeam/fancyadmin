# Dashboard

Přehled administrace jako mřížka widgetů. Rozložení je v kódu projektu (presenter), ne v konfiguraci
ani v databázi; data každého widgetu dodává `callable`, které se zavolá až při vykreslení a jen jednou.

## Co je kde

| soubor | co dělá |
|---|---|
| `src/UI/Components/Controls/Dashboard/DashboardControl.php` | control - mřížka, přidávání widgetů, pořadí, šířka |
| `src/UI/Components/Controls/Dashboard/DashboardControl.latte` | markup mřížky a widgetů |
| `src/UI/Components/Controls/Dashboard/*Widget.php` | widgety `stat`, `tiles`, `table`, `template` |
| `src/UI/Presenters/Dashboard.php` | trait presenteru - komponenta `dashboard` z továrny `DashboardControlFactory` (registruje ji rozšíření) |
| `assets/scss/_dashboard.scss` | vzhled (součást `app.scss`), prefix `.fa-dashboard-*`, světlý i tmavý režim |
| `src/lang/fcadmin.*.yml` | `fcadmin.dashboard.empty` - text prázdné tabulky |

Starší třídy `.dashboard-card` v `_theme.scss` s dashboardem nesouvisí a zůstávají.

## Použití

```php
use ADT\FancyAdmin\UI\Components\Controls\Dashboard\DashboardControl;
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
			'period' => 'Období',
			'orders' => new DashboardTableColumn('Objednávky', numeric: true),
		], fn() => $this->stats->getSalesRows())
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
- `setTitle()`, `setDescription()` - hlavička widgetu. Jméno widgetu (`data-dashboard-widget`) smí obsahovat
  písmena, číslice, `-` a `_` a musí být na dashboardu jedinečné.

## Widgety

| metoda | widget |
|---|---|
| `addStat($name, $title, fn() => int\|float\|string)` | velké číslo; `setNote()` (text nebo callable), `setIcon()`, `setLink()` (celá karta je odkaz), `setTone()` |
| `addTiles($name, fn() => DashboardTile[], ?$title)` | řada dlaždic bez společné karty (KPI); `DashboardTile`: label, value, note, icon, link, tone |
| `addTable($name, $title, $columns, fn() => iterable<array>)` | tabulka; sloupec je popisek nebo `DashboardTableColumn($label, numeric: true)` (formát a zarovnání doprava), hodnota `Nette\Utils\Html` se neescapuje; prázdná tabulka ukáže `fcadmin.dashboard.empty` |
| `addTemplate($name, ?$title, $file, array\|callable $parameters)` | vlastní šablona v kartě; dostane `$presenter`, `$dashboard`, `$widget` a parametry. Komponentu presenteru vykreslí `{control $presenter['exportForm']}` |

- **Čísla** se vypisují česky: mezera (nezlomitelná) mezi tisíci, desetinná čárka, desetinné číslo na 2 místa.
  Text projde beze změny - když má projekt vlastní formát (měna, procenta), vrátí rovnou text.
- **Ikona** je jméno Font Awesome (`truck` → `fa-solid fa-truck`), plné třídy (`fa-regular fa-box`) zůstanou.
- **Tón** (`DashboardTone`): `Neutral`, `Primary`, `Success`, `Warning`, `Danger`, `Info` - barva ikony.
