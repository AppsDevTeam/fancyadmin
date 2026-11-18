<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

use UnexpectedValueException;

/** Graf (Chart.js přes builtin `Chart` z adt-js-components). */
final class ChartWidget extends DashboardWidget
{
	/** @var callable(?DashboardPeriod): mixed projekt může vrátit cokoli, kontroluje se při vykreslení */
	private $chart;

	private ?DashboardChart $resolvedChart = null;

	/** @param callable(?DashboardPeriod): DashboardChart $chart */
	public function __construct(string $name, string $title, callable $chart)
	{
		parent::__construct($name, $title);
		$this->chart = $chart;
	}

	public function getType(): string
	{
		return 'chart';
	}

	public function getChart(): DashboardChart
	{
		if ($this->resolvedChart === null) {
			$chart = ($this->chart)($this->getPeriod());
			if (!$chart instanceof DashboardChart) {
				throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet " . DashboardChart::class . '.');
			}
			$this->resolvedChart = $chart;
		}
		return $this->resolvedChart;
	}
}
