<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

/** Jedna dlaždice widgetu `tiles`: číslo s popiskem, poznámkou a ikonou. */
final class DashboardTile
{
	public function __construct(
		public readonly string $label,
		public readonly int|float|string $value,
		public readonly ?string $note = null,
		public readonly ?string $icon = null,
		public readonly ?string $link = null,
		public readonly DashboardTone $tone = DashboardTone::Neutral,
	) {
	}
}
