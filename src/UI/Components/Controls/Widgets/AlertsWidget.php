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

	private bool $dots = true;

	private bool $inlineNotes = false;

	private bool $cards = false;

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
	public function setCompact(bool $compact = true, bool $dots = true, bool $inlineNotes = false): static
	{
		$this->compact = $compact;
		$this->dots = $dots;
		$this->inlineNotes = $inlineNotes;
		return $this;
	}

	/** Kompaktní seznam s poznámkou šedě za textem na stejném řádku (`setCompact(inlineNotes: true)`). */
	public function hasInlineNotes(): bool
	{
		return $this->compact && $this->inlineNotes;
	}

	/** Kompaktní seznam bez teček (např. přehled podle typu s počtem vpravo). */
	public function hasDots(): bool
	{
		return $this->dots;
	}

	/**
	 * Každé upozornění jako samostatná podbarvená karta (bez společné karty widgetu): ikona, text se štítky,
	 * poznámka, pod nimi proklik jako tlačítko (popisek `linkLabel`) a vpravo šedý doplněk `aside`.
	 */
	public function setCards(bool $cards = true): static
	{
		$this->cards = $cards;
		return $this;
	}

	public function isCards(): bool
	{
		return $this->cards;
	}

	private bool $agenda = false;

	/**
	 * Agenda dne („Co mám dnes udělat“): každá položka v rámečku - vlevo čas (`value`, např. „9:00“), text,
	 * pod ním poznámka a vpravo tlačítko odkazu s popiskem `linkLabel`. Tón `Warning` / `Danger` = zvýrazněná
	 * položka (podbarvená, poznámka v barvě tónu, plné tlačítko), ostatní šedé s bílým tlačítkem.
	 */
	public function setAgenda(bool $agenda = true): static
	{
		$this->agenda = $agenda;
		return $this;
	}

	public function isAgenda(): bool
	{
		return $this->agenda;
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
