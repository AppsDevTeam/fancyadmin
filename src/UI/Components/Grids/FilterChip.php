<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

/** Čip aktivního filtru v pruhu nad gridem (ColumnFiltersTrait). */
final class FilterChip
{
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $value,
		public readonly bool $default,
		public readonly ?string $column,
		public readonly bool $columnVisible,
	) {
	}

	/** Filtr na skrytém sloupci nebo bez sloupce: jeho panel se vykreslí v čipu, hlavička ho nemá. */
	public function hasOwnPanel(): bool
	{
		return !$this->columnVisible;
	}
}
