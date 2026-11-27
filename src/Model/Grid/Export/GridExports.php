<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Grid\Export;

use InvalidArgumentException;

/** Všechny služby GridExport projektu podle názvu - worker podle něj najde obsah exportu. */
final class GridExports
{
	/** @var array<string, GridExport> */
	private array $exports = [];

	/** @param list<GridExport> $exports */
	public function __construct(array $exports = [])
	{
		foreach ($exports as $export) {
			$this->exports[$export->getName()] = $export;
		}
	}

	public function get(string $name): GridExport
	{
		return $this->exports[$name] ?? throw new InvalidArgumentException(sprintf('Export gridu "%s" neexistuje.', $name));
	}
}
