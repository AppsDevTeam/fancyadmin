<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use DateTimeInterface;
use Stringable;

/**
 * Obsah exportu gridu (panel Export, docs/datagrid-export.md). Žije mimo komponentu gridu: soubor pro e-mail
 * vyrábí worker fronty (adt/exporter), kde žádný grid není. Projekt ho registruje jako službu.
 */
abstract class GridExport
{
	/** Klíč exportu u adt/exporter, název listu a základ názvu souboru. */
	abstract public function getName(): string;

	/**
	 * Hlavička celého exportu s vlastními sloupci (všechna pole, jazyky). Null = celý export jsou všechny
	 * sloupce gridu.
	 *
	 * @return list<string>|null
	 */
	public function getFullHeader(): ?array
	{
		return null;
	}

	/**
	 * Řádky celého exportu jedné dávky entit, ve stejném pořadí sloupců jako getFullHeader().
	 *
	 * @param list<object> $items
	 * @return list<list<mixed>>
	 */
	public function getFullRows(array $items): array
	{
		return [];
	}

	/** Šířka sloupců celého exportu podle obsahu - jen u krátkých hodnot, dlouhé texty by list roztáhly. */
	public function isFullAutoSize(): bool
	{
		return false;
	}

	/** Hodnota buňky sloupce gridu. Výchozí čte getter podle klíče sloupce. */
	public function getValue(object $item, string $key): mixed
	{
		foreach (['get', 'is'] as $prefix) {
			if (method_exists($item, $prefix . ucfirst($key))) {
				return self::formatValue($item->{$prefix . ucfirst($key)}());
			}
		}

		return null;
	}

	/** Datum na text, bool zůstává - generátor ho přeloží na Ano/Ne. */
	protected static function formatValue(mixed $value): mixed
	{
		return match (true) {
			$value instanceof DateTimeInterface => $value->format($value->format('His') === '000000' ? 'j. n. Y' : 'j. n. Y H:i'),
			is_scalar($value), $value === null => $value,
			$value instanceof Stringable => (string) $value,
			default => null,
		};
	}
}
