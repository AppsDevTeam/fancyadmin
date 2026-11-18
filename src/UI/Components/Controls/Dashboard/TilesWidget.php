<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

use UnexpectedValueException;

/** Řada dlaždic (KPI); bez titulku se vykreslí bez hlavičky. */
final class TilesWidget extends DashboardWidget
{
	/** @var callable(?DashboardPeriod): iterable<mixed> projekt může vrátit cokoli, kontroluje se při vykreslení */
	private $tiles;

	/** @var list<DashboardTile>|null */
	private ?array $resolvedTiles = null;

	/** @param callable(?DashboardPeriod): iterable<DashboardTile> $tiles */
	public function __construct(string $name, callable $tiles, ?string $title = null)
	{
		parent::__construct($name, $title);
		$this->tiles = $tiles;
	}

	public function getType(): string
	{
		return 'tiles';
	}

	/** @return list<DashboardTile> */
	public function getTiles(): array
	{
		if ($this->resolvedTiles === null) {
			$this->resolvedTiles = [];
			foreach (($this->tiles)($this->getPeriod()) as $tile) {
				if (!$tile instanceof DashboardTile) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet dlaždice " . DashboardTile::class . '.');
				}
				$this->resolvedTiles[] = $tile;
			}
		}
		return $this->resolvedTiles;
	}
}
