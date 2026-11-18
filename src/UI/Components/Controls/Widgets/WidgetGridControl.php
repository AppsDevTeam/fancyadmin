<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use InvalidArgumentException;
use Nette\Application\UI\Control;

/**
 * Mřížka widgetů (docs/widgets.md) - kdekoli v administraci, víc instancí na stránce. Rozložení skládá
 * projekt v kódu (přehled přes trait `Dashboard` presenteru), data widgetů se načtou až při vykreslení.
 */
class WidgetGridControl extends Control
{
	public const MAX_COLUMNS = 6;

	private int $columns = 3;

	/** @var array<string, Widget> */
	private array $widgets = [];

	public function setColumns(int $columns): static
	{
		if ($columns < 1 || $columns > self::MAX_COLUMNS) {
			throw new InvalidArgumentException('Mřížka widgetů má 1 až ' . self::MAX_COLUMNS . ' sloupců.');
		}
		$this->columns = $columns;
		return $this;
	}

	public function getColumns(): int
	{
		return $this->columns;
	}

	/** @param callable(?WidgetPeriod): (int|float|string) $value */
	public function addStat(string $name, string $title, callable $value): StatWidget
	{
		return $this->addWidget(new StatWidget($name, $title, $value));
	}

	/** @param callable(?WidgetPeriod): iterable<WidgetTile> $tiles */
	public function addTiles(string $name, callable $tiles, ?string $title = null): TilesWidget
	{
		return $this->addWidget(new TilesWidget($name, $tiles, $title));
	}

	/**
	 * @param array<string, string|WidgetTableColumn> $columns
	 * @param callable(?WidgetPeriod): iterable<array<string, mixed>> $rows
	 */
	public function addTable(string $name, string $title, array $columns, callable $rows): TableWidget
	{
		return $this->addWidget(new TableWidget($name, $title, $columns, $rows));
	}

	/** @param callable(?WidgetPeriod): WidgetChart $chart */
	public function addChart(string $name, string $title, callable $chart): ChartWidget
	{
		return $this->addWidget(new ChartWidget($name, $title, $chart));
	}

	/** @param array<string, mixed>|(\Closure(?WidgetPeriod): array<string, mixed>) $parameters */
	public function addTemplate(string $name, ?string $title, string $file, array|\Closure $parameters = []): TemplateWidget
	{
		return $this->addWidget(new TemplateWidget($name, $title, $file, $parameters));
	}

	/**
	 * @template T of Widget
	 * @param T $widget
	 * @return T
	 */
	public function addWidget(Widget $widget): Widget
	{
		if (isset($this->widgets[$widget->getName()])) {
			throw new InvalidArgumentException("Widget '{$widget->getName()}' už v mřížce je.");
		}
		$this->widgets[$widget->getName()] = $widget;
		$this->addComponent(new WidgetControl($widget), $widget->getName());
		return $widget;
	}

	public function getWidget(string $name): Widget
	{
		return $this->widgets[$name] ?? throw new InvalidArgumentException("Widget '$name' v mřížce není.");
	}

	/** @return list<Widget> seřazené podle pozice, se stejnou pozicí v pořadí přidání */
	public function getWidgets(): array
	{
		$widgets = array_values($this->widgets);
		usort($widgets, fn(Widget $a, Widget $b): int => $a->getPosition() <=> $b->getPosition());
		return $widgets;
	}

	public function getSpan(Widget $widget): int
	{
		return min($widget->getSpan(), $this->columns);
	}

	public function render(): void
	{
		$this->template->widgetGrid = $this;
		$this->template->setFile(__DIR__ . '/WidgetGridControl.latte');
		$this->template->render();
	}
}
