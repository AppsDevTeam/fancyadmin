<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/** Seznam upozornění k řešení; prázdný seznam ukáže `fcadmin.widgets.alerts.empty`. */
final class AlertsWidget extends Widget
{
	/** @var callable(?WidgetPeriod): iterable<mixed> */
	private $alerts;

	/** @var list<WidgetAlert>|null */
	private ?array $resolvedAlerts = null;

	/** @param callable(?WidgetPeriod): iterable<WidgetAlert> $alerts */
	public function __construct(string $name, string $title, callable $alerts)
	{
		parent::__construct($name, $title);
		$this->alerts = $alerts;
	}

	public function getType(): string
	{
		return 'alerts';
	}

	/** @return list<WidgetAlert> */
	public function getAlerts(): array
	{
		if ($this->resolvedAlerts === null) {
			$this->resolvedAlerts = [];
			foreach (($this->alerts)($this->getPeriod()) as $alert) {
				if (!$alert instanceof WidgetAlert) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet upozornění " . WidgetAlert::class . '.');
				}
				$this->resolvedAlerts[] = $alert;
			}
		}
		return $this->resolvedAlerts;
	}
}
