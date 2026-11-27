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

	private bool $bars = false;

	/** @var (\Closure(?WidgetPeriod): ?WidgetCallout)|null */
	private ?\Closure $insight = null;

	/**
	 * Kroky jako vodorovné pruhy pod sebou (popisek, pruh poměrem k největší hodnotě, hodnota, přechod
	 * z předchozího a poznámka v barvě tónu - „28 % · síť 26 %“). Krok s tónem `Danger` má i pruh červeně.
	 */
	public function setBars(bool $bars = true): static
	{
		$this->bars = $bars;
		return $this;
	}

	public function isBars(): bool
	{
		return $this->bars;
	}

	/** Zvýrazněný pruh pod kroky („Kde ztrácím: schůzky → nábory 31 % …“) z `headline` a `text`; null = bez pruhu. */
	public function setInsight(?\Closure $insight): static
	{
		$this->insight = $insight;
		return $this;
	}

	public function getInsight(): ?WidgetCallout
	{
		return $this->insight !== null ? ($this->insight)($this->getPeriod()) : null;
	}

	/** Největší hodnota kroků (šířka pruhů). */
	public function getMaxValue(): float
	{
		return (float) max([0, ...array_map(fn(WidgetFunnelStep $step) => is_numeric($step->value) ? (float) $step->value : 0.0, $this->getSteps())]);
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
