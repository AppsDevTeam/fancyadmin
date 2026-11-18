<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

/** Sloupec widgetu `table`; číselný se formátuje a zarovná doprava i s hlavičkou. */
final class DashboardTableColumn
{
	public function __construct(
		public readonly string $label,
		public readonly bool $numeric = false,
	) {
	}
}
