<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/**
 * Seznam upozornění k řešení; prázdný seznam ukáže `fcadmin.widgets.alerts.empty`. Volitelně kompaktní
 * seznam (setCompact) a jen prvních N položek s odkazem „Zobrazit všech“ (setLimit).
 */
final class AlertsWidget extends Widget
{
	/** @var callable(?WidgetPeriod): iterable<mixed> */
	private $alerts;

	/** @var list<WidgetAlert>|null */
	private ?array $resolvedAlerts = null;

	private bool $compact = false;

	private ?int $limit = null;

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

	/**
	 * Kompaktní seznam: místo ikony a podbarvení řádku barevná tečka, položky ve dvou sloupcích oddělené linkou,
	 * hodnota vpravo v barvě tónu. Odkaz položky je celý řádek (bez popisku „Zobrazit podrobnosti“).
	 */
	public function setCompact(bool $compact = true): static
	{
		$this->compact = $compact;
		return $this;
	}

	public function isCompact(): bool
	{
		return $this->compact;
	}

	/** Vidět je jen prvních `$limit` položek, zbytek ukáže tlačítko „Zobrazit všech N“ (a skryje „Zobrazit méně“). */
	public function setLimit(?int $limit): static
	{
		$this->limit = $limit !== null && $limit > 0 ? $limit : null;
		return $this;
	}

	public function getLimit(): ?int
	{
		return $this->limit;
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
