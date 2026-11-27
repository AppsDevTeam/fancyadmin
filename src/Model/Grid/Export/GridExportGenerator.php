<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use ADT\Exporter\Model\Service\BatchedExportFileGenerator;
use Generator;
use InvalidArgumentException;
use Nette\Localization\Translator;
use Nette\Utils\FileSystem;
use Nette\Utils\Random;
use Stringable;

/**
 * Soubor exportu gridu po dávkách (adt/exporter). Identifier exportu je název GridExport, jediná sekce říká,
 * které sloupce: SECTION_COLUMNS nese sloupce gridu (klíč => nadpis), SECTION_FULL celý export s vlastními sloupci.
 */
abstract class GridExportGenerator implements BatchedExportFileGenerator
{
	public const string SECTION_COLUMNS = 'columns';
	public const string SECTION_FULL = 'full';

	private const int AUTO_SIZE_MAX_WIDTH = 60;

	public function __construct(
		private readonly GridExports $exports,
		private readonly Translator $translator,
		private readonly string $tempDir,
	) {
	}

	abstract public function getFormat(): string;

	/**
	 * @param iterable<list<scalar|null>> $rows první řádek je hlavička
	 * @param list<int>|null $widths
	 */
	abstract protected function write(string $path, string $sheetName, iterable $rows, ?array $widths): void;

	public function generateBatched(array $sections, string $identifier): string
	{
		if (count($sections) !== 1) {
			throw new InvalidArgumentException('Export gridu má právě jednu sekci.');
		}

		$export = $this->exports->get($identifier);
		$type = (string) array_key_first($sections);
		$rows = $this->createRows($export, $type, $sections[$type]);
		$widths = null;
		if ($type === self::SECTION_FULL && $export->isFullAutoSize()) {
			$rows = iterator_to_array($rows, false);
			$widths = self::getAutoSizeWidths($rows);
		}

		// Název dočasného souboru je i názvem staženého souboru, proto vlastní adresář pro každý export
		$dir = $this->tempDir . '/grid-export';
		foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $old) {
			@rmdir($old);
		}
		$path = $dir . '/' . Random::generate(16) . '/' . $export->getName() . '.' . $this->getFormat();
		FileSystem::createDir(dirname($path));
		$this->write($path, $export->getName(), $rows, $widths);

		return $path;
	}

	/**
	 * Entity platí jen v právě procházené dávce (worker mezi dávkami čistí EntityManager), proto se z nich
	 * hodnoty berou hned.
	 *
	 * @param array{batches: iterable<list<object>>, columns: array<string, string>} $section
	 * @return Generator<list<scalar|null>>
	 */
	private function createRows(GridExport $export, string $type, array $section): Generator
	{
		$full = $type === self::SECTION_FULL;
		$keys = array_map('strval', array_keys($section['columns']));

		yield $full ? $export->getFullHeader() ?? [] : array_values($section['columns']);

		foreach ($section['batches'] as $items) {
			$rows = $full
				? $export->getFullRows($items)
				: array_map(fn(object $item): array => array_map(fn(string $key): mixed => $export->getValue($item, $key), $keys), $items);
			foreach ($rows as $row) {
				yield array_map($this->formatCell(...), $row);
			}
		}
	}

	private function formatCell(mixed $value): string|int|float|null
	{
		return match (true) {
			is_bool($value) => $this->translator->translate($value ? 'fcadmin.datagrid.columnFilters.yes' : 'fcadmin.datagrid.columnFilters.no'),
			is_string($value), is_int($value), is_float($value), $value === null => $value,
			$value instanceof Stringable => (string) $value,
			default => null,
		};
	}

	/**
	 * @param list<list<scalar|null>> $rows
	 * @return list<int>
	 */
	private static function getAutoSizeWidths(array $rows): array
	{
		$widths = [];
		foreach ($rows as $row) {
			foreach ($row as $index => $value) {
				$widths[$index] = max($widths[$index] ?? 0, min(mb_strlen((string) $value) + 2, self::AUTO_SIZE_MAX_WIDTH));
			}
		}

		return array_values($widths);
	}
}
