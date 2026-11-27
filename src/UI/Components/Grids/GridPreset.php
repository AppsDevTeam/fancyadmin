<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids;

/** Uložená sestava gridu: filtry a nastavení sloupců pod názvem, soukromá nebo sdílená s týmem. */
final class GridPreset
{
	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $columns
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly bool $shared,
		public readonly bool $own,
		public readonly bool $editable,
		public readonly ?string $author,
		public readonly array $filters,
		public readonly array $columns,
	) {
	}
}
