<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/**
 * Zvýrazněná karta s jedním sdělením („Kde je problém“): podbarvená tónem (setTone, výchozí Warning), nadpis
 * v barvě tónu, výrazné sdělení, vysvětlení a seznam položek. Data `null` = karta s hláškou prázdných dat.
 */
final class CalloutWidget extends Widget
{
	/** @var callable(?WidgetPeriod): ?WidgetCallout */
	private $callout;

	private bool $resolved = false;

	private ?WidgetCallout $resolvedCallout = null;

	private WidgetTone $tone = WidgetTone::Warning;

	/** @param callable(?WidgetPeriod): ?WidgetCallout $callout */
	public function __construct(string $name, string $title, callable $callout)
	{
		parent::__construct($name, $title);
		$this->callout = $callout;
	}

	public function getType(): string
	{
		return 'callout';
	}

	public function setTone(WidgetTone $tone): static
	{
		$this->tone = $tone;
		return $this;
	}

	public function getTone(): WidgetTone
	{
		return $this->tone;
	}

	public function getCallout(): ?WidgetCallout
	{
		if (!$this->resolved) {
			$callout = ($this->callout)($this->getPeriod());
			if ($callout !== null && !$callout instanceof WidgetCallout) {
				throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet " . WidgetCallout::class . ' nebo null.');
			}
			$this->resolvedCallout = $callout;
			$this->resolved = true;
		}
		return $this->resolvedCallout;
	}
}
