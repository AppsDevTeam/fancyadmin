<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/**
 * KPI karta: velká hodnota s jednotkou, křivka vývoje (sparkline), štítky a popisek. Dělená varianta
 * (`WidgetGridControl::addKpiParts()`) má místo jedné hodnoty několik částí vedle sebe oddělených linkou.
 */
final class KpiWidget extends Widget
{
	/** @var (callable(?WidgetPeriod): (int|float|string|null))|null */
	private $value;

	/** @var (callable(?WidgetPeriod): iterable<mixed>)|null */
	private $parts;

	private bool $resolved = false;

	private int|float|string|null $resolvedValue = null;

	/** @var list<WidgetFigure>|null */
	private ?array $resolvedParts = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $unit = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $note = null;

	/** @var (\Closure(?WidgetPeriod): list<int|float|null>)|null */
	private ?\Closure $sparkline = null;

	private WidgetTone $tone = WidgetTone::Neutral;

	/** @var list<WidgetSplit>|(\Closure(?WidgetPeriod): list<WidgetSplit>) */
	private array|\Closure $split = [];

	private bool $detail = false;

	/**
	 * @param (callable(?WidgetPeriod): (int|float|string|null))|null $value
	 * @param (callable(?WidgetPeriod): iterable<WidgetFigure>)|null $parts
	 */
	public function __construct(string $name, string $title, ?callable $value = null, ?callable $parts = null)
	{
		parent::__construct($name, $title);
		if (($value === null) === ($parts === null)) {
			throw new \InvalidArgumentException('KPI widget má buď hodnotu, nebo části.');
		}
		$this->value = $value;
		$this->parts = $parts;
	}

	public function getType(): string
	{
		return 'kpi';
	}

	public function hasParts(): bool
	{
		return $this->parts !== null;
	}

	public function getValue(): int|float|string|null
	{
		if (!$this->resolved && $this->value !== null) {
			$this->resolvedValue = ($this->value)($this->getPeriod());
			$this->resolved = true;
		}
		return $this->resolvedValue;
	}

	/** @return list<WidgetFigure> */
	public function getParts(): array
	{
		if ($this->resolvedParts === null) {
			$this->resolvedParts = [];
			foreach ($this->parts !== null ? ($this->parts)($this->getPeriod()) : [] as $part) {
				if (!$part instanceof WidgetFigure) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet části " . WidgetFigure::class . '.');
				}
				$this->resolvedParts[] = $part;
			}
		}
		return $this->resolvedParts;
	}

	/** @param string|(\Closure(?WidgetPeriod): ?string)|null $unit jednotka za hodnotou; Closure, když závisí na datech (tis. / mil. Kč) */
	public function setUnit(string|\Closure|null $unit): static
	{
		$this->unit = $unit;
		return $this;
	}

	public function getUnit(): ?string
	{
		return $this->unit instanceof \Closure ? ($this->unit)($this->getPeriod()) : $this->unit;
	}

	/** @param string|(\Closure(?WidgetPeriod): ?string)|null $note popisek pod hodnotou (z čeho číslo je, stav k datu) */
	public function setNote(string|\Closure|null $note): static
	{
		$this->note = $note;
		return $this;
	}

	public function getNote(): ?string
	{
		return $this->note instanceof \Closure ? ($this->note)($this->getPeriod()) : $this->note;
	}

	/** @param (\Closure(?WidgetPeriod): list<int|float|null>)|null $sparkline hodnoty křivky vývoje vedle hodnoty (null = mezera) */
	public function setSparkline(?\Closure $sparkline): static
	{
		$this->sparkline = $sparkline;
		return $this;
	}

	/**
	 * Body křivky vývoje v SVG 72 × 28 (okraj 3 px kvůli koncovému bodu), null bez křivky nebo s méně než
	 * dvěma hodnotami.
	 *
	 * @return array{points: string, x: float, y: float}|null
	 */
	public function getSparkline(): ?array
	{
		if ($this->sparkline === null) {
			return null;
		}
		$values = array_values(array_filter(($this->sparkline)($this->getPeriod()), fn($value) => $value !== null));
		if (count($values) < 2) {
			return null;
		}
		$min = min($values);
		$range = max($values) - $min ?: 1;
		$step = 66 / (count($values) - 1);
		$points = [];
		foreach ($values as $i => $value) {
			$points[] = [round(3 + $i * $step, 1), round(25 - ($value - $min) / $range * 22, 1)];
		}
		$last = end($points);

		return [
			'points' => implode(' ', array_map(fn(array $point) => $point[0] . ',' . $point[1], $points)),
			'x' => $last[0],
			'y' => $last[1],
		];
	}

	/** Barva hodnoty (Neutral = běžný text) a křivky. */
	public function setTone(WidgetTone $tone): static
	{
		$this->tone = $tone;
		return $this;
	}

	public function getTone(): WidgetTone
	{
		return $this->tone;
	}

	/**
	 * Pruh poměru pod hodnotou s legendou (prodej / pronájem, exkluzivní / neexkluzivní).
	 *
	 * @param list<WidgetSplit>|(\Closure(?WidgetPeriod): list<WidgetSplit>) $split
	 */
	public function setSplit(array|\Closure $split): static
	{
		$this->split = $split;
		return $this;
	}

	/** @return list<array{split: WidgetSplit, share: float}> části s podílem na součtu (0–1) */
	public function getSplit(): array
	{
		$split = $this->split instanceof \Closure ? ($this->split)($this->getPeriod()) : $this->split;
		$total = array_sum(array_map(fn(WidgetSplit $part) => max(0, $part->value), $split));
		return array_map(fn(WidgetSplit $part) => ['split' => $part, 'share' => $total > 0 ? max(0, $part->value) / $total : 0.0], array_values($split));
	}

	/** Dlaždice detailu (podstránky): menší hodnota, popisek a pruh pod ní - na dashboardu velká KPI karta. */
	public function setDetail(bool $detail = true): static
	{
		$this->detail = $detail;
		return $this;
	}

	public function isDetail(): bool
	{
		return $this->detail;
	}

	private bool $accent = false;

	/** @var list<WidgetFigure>|(\Closure(?WidgetPeriod): list<WidgetFigure>) */
	private array|\Closure $values = [];

	private bool $splitSolid = false;

	/**
	 * Řada hodnot místo jedné („17 makléřů / 14 aktivních / 9 produktivních / 4 rizikoví“): hodnota v barvě tónu
	 * údaje a za ní jednotka (`unit`), oddělené lomítkem. Popisek údaje je jen bublina (`title`).
	 *
	 * @param list<WidgetFigure>|(\Closure(?WidgetPeriod): list<WidgetFigure>) $values
	 */
	public function setValues(array|\Closure $values): static
	{
		$this->values = $values;
		return $this;
	}

	/** @return list<WidgetFigure> */
	public function getValues(): array
	{
		return array_values($this->values instanceof \Closure ? ($this->values)($this->getPeriod()) : $this->values);
	}

	/** Pruh poměru plnými barvami tónů a legenda jen z popisků šedě za sebou (bez počtů a podílů). */
	public function setSplitSolid(bool $solid = true): static
	{
		$this->splitSolid = $solid;
		return $this;
	}

	public function isSplitSolid(): bool
	{
		return $this->splitSolid;
	}

	/** Barevná linka nahoře na kartě v tónu karty (souhrn na podstránce: tržba zeleně, rezervace oranžově…). */
	public function setAccent(bool $accent = true): static
	{
		$this->accent = $accent;
		return $this;
	}

	public function isAccent(): bool
	{
		return $this->accent;
	}
}
