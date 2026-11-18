<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/** Řada dlaždic (KPI); bez titulku se vykreslí bez hlavičky. */
final class TilesWidget extends Widget
{
	/** @var callable(?WidgetPeriod): iterable<mixed> projekt může vrátit cokoli, kontroluje se při vykreslení */
	private $tiles;

	/** @var list<WidgetTile>|null */
	private ?array $resolvedTiles = null;

	/** @param callable(?WidgetPeriod): iterable<WidgetTile> $tiles */
	public function __construct(string $name, callable $tiles, ?string $title = null)
	{
		parent::__construct($name, $title);
		$this->tiles = $tiles;
	}

	public function getType(): string
	{
		return 'tiles';
	}

	/** @return list<WidgetTile> */
	public function getTiles(): array
	{
		if ($this->resolvedTiles === null) {
			$this->resolvedTiles = [];
			foreach (($this->tiles)($this->getPeriod()) as $tile) {
				if (!$tile instanceof WidgetTile) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet dlaždice " . WidgetTile::class . '.');
				}
				$this->resolvedTiles[] = $tile;
			}
		}
		return $this->resolvedTiles;
	}
}
