<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use ADT\Datagrid\Model\Export\Excel\SafeXlsxWriter;
use XLSXWriter_BuffererWriter;

final class GridExportXlsxGenerator extends GridExportGenerator
{
	public const string FORMAT = 'xlsx';

	private const int MAX_SHEET_NAME_LENGTH = 31;

	public function getFormat(): string
	{
		return self::FORMAT;
	}

	protected function write(string $path, string $sheetName, iterable $rows, ?array $widths): void
	{
		$writer = new class extends SafeXlsxWriter {
			/**
			 * Číselný text bez úvodní nuly a do 15 číslic je číslo, ostatní text zůstane textem - knihovna by
			 * jinak udělala číslo i z delšího kódu a Excel by ho zaokrouhlil.
			 */
			protected function writeCell(XLSXWriter_BuffererWriter &$file, $row_number, $column_number, $value, $num_format_type, $cell_style_idx)
			{
				if (is_string($value) && !(preg_match('~^-?(0|[1-9]\d*)(\.\d+)?$~', $value) && strlen((string) preg_replace('~\D~', '', $value)) <= 15)) {
					$num_format_type = 'n_string';
				}

				parent::writeCell($file, $row_number, $column_number, $value, $num_format_type, $cell_style_idx);
			}
		};

		$sheet = mb_substr($sheetName, 0, self::MAX_SHEET_NAME_LENGTH);
		$header = true;
		foreach ($rows as $row) {
			if ($header) {
				$writer->writeSheetHeader($sheet, array_fill(0, max(count($row), 1), 'GENERAL'), ['suppress_row' => true, 'widths' => $widths ?? []]);
				$writer->writeSheetRow($sheet, $row, ['font-style' => 'bold']);
				$header = false;
				continue;
			}
			$writer->writeSheetRow($sheet, $row);
		}

		$writer->writeToFile($path);
	}
}
