<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use ADT\Datagrid\Model\Export\FormulaGuard;

/** CSV pro Excel: středník, CRLF a BOM, aby Excel poznal UTF-8. Text nesmí začít jako vzorec (FormulaGuard). */
final class GridExportCsvGenerator extends GridExportGenerator
{
	public const string FORMAT = 'csv';

	public function getFormat(): string
	{
		return self::FORMAT;
	}

	protected function write(string $path, string $sheetName, iterable $rows, ?array $widths): void
	{
		$handle = fopen($path, 'w');
		fwrite($handle, "\xEF\xBB\xBF");
		foreach ($rows as $row) {
			fputcsv($handle, FormulaGuard::escapeRows([$row])[0], ';', '"', '', "\r\n");
		}
		fclose($handle);
	}
}
