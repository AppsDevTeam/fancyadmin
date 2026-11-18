<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/** Graf (Chart.js přes builtin `Chart` z adt-js-components). */
final class ChartWidget extends Widget
{
	/** @var callable(?WidgetPeriod): mixed projekt může vrátit cokoli, kontroluje se při vykreslení */
	private $chart;

	private ?WidgetChart $resolvedChart = null;

	/** @param callable(?WidgetPeriod): WidgetChart $chart */
	public function __construct(string $name, string $title, callable $chart)
	{
		parent::__construct($name, $title);
		$this->chart = $chart;
	}

	public function getType(): string
	{
		return 'chart';
	}

	public function getChart(): WidgetChart
	{
		if ($this->resolvedChart === null) {
			$chart = ($this->chart)($this->getPeriod());
			if (!$chart instanceof WidgetChart) {
				throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet " . WidgetChart::class . '.');
			}
			$this->resolvedChart = $chart;
		}
		return $this->resolvedChart;
	}
}
