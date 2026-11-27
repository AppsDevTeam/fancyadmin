# Export z panelu gridu (adt/exporter)

Panel Export filtrů gridu ([datagrid-column-filters.md](datagrid-column-filters.md)) napojený na adt/exporter:
XLSX nebo CSV podle filtrů a hledání, se zobrazenými nebo všemi sloupci. Ke stažení hned, na e-mail vždy na
pozadí; stažení nad `exporter.syncRowLimit` jde na pozadí taky a e-mail dostane přihlášený. Soubor z odkazu
v e-mailu stáhne jen ten, kdo export zadal.

## Co dodá fancyadmin

| Část | Třída |
|---|---|
| Obsah exportu (hodnoty buněk, celý export s vlastními sloupci) | `Model\Grid\Export\GridExport` - projekt dědí |
| Registr exportů podle názvu (worker fronty nemá grid) | `GridExports`, plní ho extension ze všech služeb `GridExport` |
| Soubor po dávkách | `GridExportXlsxGenerator`, `GridExportCsvGenerator` (služby registruje extension) |
| Autor exportu | `GridExportOwner` - extension ho přidá do `$onExport` exporteru |
| E-mail s přílohou a odkazem | `GridExportMailFactory` - registruje projekt (viz níže) |
| Panel v gridu | `BaseGridTrait::addExportPanel($grid, $export)` |
| Stažení z odkazu v e-mailu | `UI\Presenters\Exports\ExportsPresenterTrait::actionDownload()` |
| Entita s autorem | `Model\Entities\Export` + `ExportTrait` |

Generátory píšou proudem (`SafeXlsxWriter` z datagrid-components), takže velký export nedrží v paměti celý
sešit. Text začínající `=` je v XLSX vždy text, v CSV dostane úvodní apostrof (`FormulaGuard`). Číselný text bez
úvodní nuly a do 15 číslic je v XLSX číslo, ostatní text zůstane textem (kód `005130`, dlouhé kódy). `bool` se
přeloží na `fcadmin.datagrid.columnFilters.yes` / `no`. CSV je pro Excel: středník, CRLF, BOM.

## Napojení v projektu

```neon
extensions:
	exporter: ADT\Exporter\DI\ExporterExtension

exporter:
	fileDir: %privateDataDir%/exports
	downloadLink: ':Admin:Export:download'
	syncRowLimit: 5000

backgroundQueue:
	callbacks:
		processExport: [@exporter.exporter, processExport]

services:
	- App\Model\Grid\Export\OrderGridExport
	# E-mail exportu; podtřída může v createMessage() doplnit odesílatele a šablonu projektu
	- ADT\FancyAdmin\Model\Grid\Export\GridExportMailFactory
```

Cron `exporter:purge-files` maže soubory po `fileRetentionDays`.

Entita:

```php
#[ORM\Entity]
class Export extends BaseEntity implements \ADT\FancyAdmin\Model\Entities\Export
{
	use \ADT\FancyAdmin\Model\Entities\ExportTrait;
}
```

Obsah exportu - výchozí `getValue()` čte getter podle klíče sloupce gridu:

```php
final class OrderGridExport extends GridExport
{
	public function getName(): string
	{
		return 'objednavky';
	}

	public function getValue(object $item, string $key): mixed
	{
		return match ($key) {
			'name' => $item->getFullName(),
			default => parent::getValue($item, $key),
		};
	}
}
```

Celý export s vlastními sloupci (všechna pole, jazyky) vrací hlavičku z `getFullHeader()` a řádky dávky
z `getFullRows()`; bez `getFullHeader()` jsou celý export všechny sloupce gridu. `isFullAutoSize()` nastaví
šířku sloupců podle obsahu (řádky se pak drží v paměti, jen pro menší exporty).

Grid:

```php
protected function initGrid(DataGrid $grid): void
{
	// ...
	$this->addExportPanel($grid, $this->orderGridExport);
}
```

Presenter stažení (cíl `downloadLink`); smazaný soubor po retenci je ve výchozím stavu 410, projekt může
v `onExportFilePurged()` třeba přesměrovat s hláškou `fcadmin.datagrid.columnFilters.export.purged`:

```php
class ExportPresenter extends AuthPresenter
{
	use ExportsPresenterTrait;
}
```

Export musí mít autora (`CreatedByInterface` nebo `CreatedByNullableInterface`), jinak ho nestáhne nikdo.
