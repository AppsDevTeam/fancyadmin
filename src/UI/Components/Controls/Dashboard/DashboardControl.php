<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

use InvalidArgumentException;
use Nette\Application\UI\Control;

/**
 * Dashboard jako mřížka widgetů (docs/dashboard.md). Rozložení skládá projekt v kódu
 * (`Dashboard` trait presenteru), data widgetů se načtou až při vykreslení.
 */
class DashboardControl extends Control
{
	public const MAX_COLUMNS = 6;

	private int $columns = 3;

	/** @var array<string, DashboardWidget> */
	private array $widgets = [];

	public function setColumns(int $columns): static
	{
		if ($columns < 1 || $columns > self::MAX_COLUMNS) {
			throw new InvalidArgumentException('Dashboard má 1 až ' . self::MAX_COLUMNS . ' sloupců.');
		}
		$this->columns = $columns;
		return $this;
	}

	public function getColumns(): int
	{
		return $this->columns;
	}

	/** @param callable(?DashboardPeriod): (int|float|string) $value */
	public function addStat(string $name, string $title, callable $value): StatWidget
	{
		return $this->addWidget(new StatWidget($name, $title, $value));
	}

	/** @param callable(?DashboardPeriod): iterable<DashboardTile> $tiles */
	public function addTiles(string $name, callable $tiles, ?string $title = null): TilesWidget
	{
		return $this->addWidget(new TilesWidget($name, $tiles, $title));
	}

	/**
	 * @param array<string, string|DashboardTableColumn> $columns
	 * @param callable(?DashboardPeriod): iterable<array<string, mixed>> $rows
	 */
	public function addTable(string $name, string $title, array $columns, callable $rows): TableWidget
	{
		return $this->addWidget(new TableWidget($name, $title, $columns, $rows));
	}

	/** @param array<string, mixed>|(\Closure(?DashboardPeriod): array<string, mixed>) $parameters */
	public function addTemplate(string $name, ?string $title, string $file, array|\Closure $parameters = []): TemplateWidget
	{
		return $this->addWidget(new TemplateWidget($name, $title, $file, $parameters));
	}

	/**
	 * @template T of DashboardWidget
	 * @param T $widget
	 * @return T
	 */
	public function addWidget(DashboardWidget $widget): DashboardWidget
	{
		if (isset($this->widgets[$widget->getName()])) {
			throw new InvalidArgumentException("Widget '{$widget->getName()}' už na dashboardu je.");
		}
		$this->widgets[$widget->getName()] = $widget;
		$this->addComponent(new DashboardWidgetControl($widget), $widget->getName());
		return $widget;
	}

	public function getWidget(string $name): DashboardWidget
	{
		return $this->widgets[$name] ?? throw new InvalidArgumentException("Widget '$name' na dashboardu není.");
	}

	/** @return list<DashboardWidget> seřazené podle pozice, se stejnou pozicí v pořadí přidání */
	public function getWidgets(): array
	{
		$widgets = array_values($this->widgets);
		usort($widgets, fn(DashboardWidget $a, DashboardWidget $b): int => $a->getPosition() <=> $b->getPosition());
		return $widgets;
	}

	public function getSpan(DashboardWidget $widget): int
	{
		return min($widget->getSpan(), $this->columns);
	}

	public function render(): void
	{
		$this->template->dashboard = $this;
		$this->template->setFile(__DIR__ . '/DashboardControl.latte');
		$this->template->render();
	}
}
