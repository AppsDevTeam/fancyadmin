<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/**
 * Trychtýř: kroky v rámečcích za sebou (zalomí se), mezi nimi šipka s přechodem z předchozího kroku.
 * Prázdný ukáže `fcadmin.widgets.empty`.
 */
final class FunnelWidget extends Widget
{
	/** @var callable(?WidgetPeriod): iterable<mixed> */
	private $steps;

	/** @var list<WidgetFunnelStep>|null */
	private ?array $resolvedSteps = null;

	/** @param callable(?WidgetPeriod): iterable<WidgetFunnelStep> $steps */
	public function __construct(string $name, string $title, callable $steps)
	{
		parent::__construct($name, $title);
		$this->steps = $steps;
	}

	public function getType(): string
	{
		return 'funnel';
	}

	/** @return list<WidgetFunnelStep> */
	public function getSteps(): array
	{
		if ($this->resolvedSteps === null) {
			$this->resolvedSteps = [];
			foreach (($this->steps)($this->getPeriod()) as $step) {
				if (!$step instanceof WidgetFunnelStep) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet kroky " . WidgetFunnelStep::class . '.');
				}
				$this->resolvedSteps[] = $step;
			}
		}
		return $this->resolvedSteps;
	}
}
