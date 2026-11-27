<?php

declare(strict_types=1);

use ADT\Exporter\Model\Entities\Export;
use ADT\Exporter\Model\Entities\ExportTrait;
use ADT\Exporter\Model\Service\ExportFileStorage;
use ADT\FancyAdmin\Model\Grid\Export\GridExport;
use ADT\FancyAdmin\Model\Grid\Export\GridExportCsvGenerator;
use ADT\FancyAdmin\Model\Grid\Export\GridExportGenerator;
use ADT\FancyAdmin\Model\Grid\Export\GridExportMailFactory;
use ADT\FancyAdmin\Model\Grid\Export\GridExports;
use ADT\FancyAdmin\Model\Grid\Export\GridExportXlsxGenerator;
use Nette\Localization\Translator;
use Nette\Utils\FileSystem;
use Tester\Assert;

/**
 * Export z panelu gridu (docs/datagrid-export.md): registr obsahu exportů, XLSX a CSV po dávkách, hodnota od
 * uživatele se nestane vzorcem, e-mail s přílohou.
 */

require __DIR__ . '/bootstrap.php';

final class TestGridExport extends GridExport
{
	public function getName(): string
	{
		return 'zakaznici';
	}

	public function getFullHeader(): ?array
	{
		return ['Jméno', 'Poznámka'];
	}

	public function getFullRows(array $items): array
	{
		return array_map(fn(object $item): array => [$item->name, str_repeat('x', 100)], $items);
	}

	public function isFullAutoSize(): bool
	{
		return true;
	}
}

final class TestGridExportItem
{
	public function __construct(
		public string $name,
		public bool $active = true,
		public mixed $price = 0,
		public string $code = '',
	) {
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

	public function getPrice(): mixed
	{
		return $this->price;
	}

	public function getCode(): string
	{
		return $this->code;
	}
}

function gridExportTranslator(): Translator
{
	return new class implements Translator {
		public function translate(string|Stringable $message, mixed ...$parameters): string
		{
			return (string) $message . ($parameters ? ' ' . json_encode($parameters[0] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
		}
	};
}

function gridExportTempDir(): string
{
	$dir = sys_get_temp_dir() . '/fancyadmin-grid-export-' . getmypid();
	FileSystem::createDir($dir);

	return $dir;
}

/** @param class-string<GridExportGenerator> $class */
function generateGridExport(string $class, string $type, array $columns): string
{
	$generator = new $class(new GridExports([new TestGridExport()]), gridExportTranslator(), gridExportTempDir());
	$batches = (function () {
		yield [new TestGridExportItem('=HYPERLINK("http://example.com")', true, 1250.5, '005130')];
		yield [new TestGridExportItem('Novák', false, '42', '1234567890123456')];
	})();

	return $generator->generateBatched([$type => ['batches' => $batches, 'columns' => $columns]], 'zakaznici');
}

function sheetXml(string $path): string
{
	$zip = new ZipArchive();
	$zip->open($path);
	$xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
	$zip->close();

	return $xml;
}

test('registr najde export podle názvu, neznámý odmítne', function () {
	$exports = new GridExports([new TestGridExport()]);

	Assert::type(TestGridExport::class, $exports->get('zakaznici'));
	Assert::exception(fn() => $exports->get('neznamy'), InvalidArgumentException::class);
});

test('XLSX: sloupce gridu, vzorec jako text, čísla jako čísla, kód s nulou a dlouhý kód jako text, bool přeložený', function () {
	$path = generateGridExport(GridExportXlsxGenerator::class, GridExportGenerator::SECTION_COLUMNS, ['name' => 'Jméno', 'active' => 'Aktivní', 'price' => 'Cena', 'code' => 'Kód']);
	$xml = sheetXml($path);

	Assert::same('zakaznici.xlsx', basename($path));
	Assert::notContains('<f>', $xml);
	Assert::contains('<t>=HYPERLINK(&quot;http://example.com&quot;)</t>', $xml);
	Assert::contains('<t>fcadmin.datagrid.columnFilters.yes</t>', $xml);
	Assert::contains('<t>fcadmin.datagrid.columnFilters.no</t>', $xml);
	Assert::contains('t="n"><v>1250.5</v>', $xml);
	Assert::contains('t="n"><v>42</v>', $xml);
	Assert::contains('<t>005130</t>', $xml);
	Assert::contains('<t>1234567890123456</t>', $xml);
	Assert::contains('<t>Jméno</t>', $xml);
});

test('XLSX: celý export s vlastní hlavičkou a šířkou sloupců podle obsahu', function () {
	$xml = sheetXml(generateGridExport(GridExportXlsxGenerator::class, GridExportGenerator::SECTION_FULL, []));

	Assert::contains('<t>Poznámka</t>', $xml);
	Assert::contains('<t>Novák</t>', $xml);
	Assert::contains('width="60"', $xml, 'dlouhý text má strop šířky');
});

test('CSV pro Excel: BOM, středník, CRLF a apostrof před vzorcem', function () {
	$csv = (string) file_get_contents(generateGridExport(GridExportCsvGenerator::class, GridExportGenerator::SECTION_COLUMNS, ['name' => 'Jméno', 'active' => 'Aktivní']));

	Assert::true(str_starts_with($csv, "\xEF\xBB\xBF"));
	Assert::contains("Jméno;Aktivní\r\n", $csv);
	Assert::contains('"\'=HYPERLINK(""http://example.com"")";fcadmin.datagrid.columnFilters.yes' . "\r\n", $csv);
	Assert::contains("Novák;fcadmin.datagrid.columnFilters.no\r\n", $csv);
});

test('export gridu má právě jednu sekci', function () {
	$generator = new GridExportCsvGenerator(new GridExports([new TestGridExport()]), gridExportTranslator(), gridExportTempDir());

	Assert::exception(fn() => $generator->generateBatched([], 'zakaznici'), InvalidArgumentException::class);
});

test('e-mail: příjemce, předmět, odkaz a soubor v příloze', function () {
	$path = generateGridExport(GridExportCsvGenerator::class, GridExportGenerator::SECTION_COLUMNS, ['name' => 'Jméno']);
	$export = new class implements Export {
		use ExportTrait;

		public function getId(): ?int
		{
			return 1;
		}
	};
	$export->setIdentifier('zakaznici')->setEmail('admin@example.com')->setFileName('zakaznici.csv')->setSections([['ids' => [1, 2]]]);
	$storage = new class ($path) implements ExportFileStorage {
		public function __construct(private string $path)
		{
		}

		public function attach(Export $export, string $tmpPath): void
		{
		}

		public function getLocalPath(Export $export): ?string
		{
			return $this->path;
		}

		public function purge(Export $export): void
		{
		}
	};

	$message = (new GridExportMailFactory($storage, gridExportTranslator()))->create($export, 'https://example.com/export/1');

	Assert::same(['admin@example.com' => null], $message->getHeader('To'));
	Assert::contains('fcadmin.datagrid.columnFilters.export.subject', (string) $message->getSubject());
	Assert::contains('https://example.com/export/1', $message->getHtmlBody());
	Assert::contains('&quot;count&quot;:2', $message->getHtmlBody());
	Assert::count(1, $message->getAttachments());
	Assert::contains('zakaznici.csv', $message->getAttachments()[0]->getHeader('Content-Disposition'));
});

FileSystem::delete(gridExportTempDir());
