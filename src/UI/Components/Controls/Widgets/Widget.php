<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use InvalidArgumentException;

/** Widget mřížky. Data dodává callable, které se zavolá až při vykreslení. */
abstract class Widget
{
	private ?string $description = null;

	private int $span = 1;

	private int $position = 0;

	private ?WidgetPeriod $defaultPeriod = null;

	/** @var list<WidgetPeriod> */
	private array $periodOptions = [];

	private ?WidgetPeriod $period = null;

	public function __construct(
		private readonly string $name,
		private ?string $title = null,
	) {
		// Jméno je zároveň jméno podkomponenty (persistentní parametr, snippet)
		if (!preg_match('~^[a-zA-Z][a-zA-Z0-9_]*$~', $name)) {
			throw new InvalidArgumentException("Neplatné jméno widgetu '$name': písmena, číslice a '_', na začátku písmeno.");
		}
	}

	abstract public function getType(): string;

	public function getName(): string
	{
		return $this->name;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setTitle(?string $title): static
	{
		$this->title = $title;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): static
	{
		$this->description = $description;
		return $this;
	}

	public function getSpan(): int
	{
		return $this->span;
	}

	/** Počet sloupců mřížky, které widget zabere (víc než má mřížka se ořízne). */
	public function setSpan(int $span): static
	{
		if ($span < 1) {
			throw new InvalidArgumentException('Widget musí zabírat aspoň jeden sloupec.');
		}
		$this->span = $span;
		return $this;
	}

	public function getPosition(): int
	{
		return $this->position;
	}

	/** Pořadí v mřížce; stejná hodnota zachová pořadí přidání. */
	public function setPosition(int $position): static
	{
		$this->position = $position;
		return $this;
	}

	/**
	 * Výběr období v hlavičce widgetu; data widgetu pak dostanou zvolené období jako parametr.
	 *
	 * @param list<WidgetPeriod>|null $options nabízená období, výchozí všechna
	 */
	public function setPeriods(WidgetPeriod $default, ?array $options = null): static
	{
		$options ??= WidgetPeriod::cases();
		if (!in_array($default, $options, true)) {
			throw new InvalidArgumentException('Výchozí období musí být mezi nabízenými.');
		}
		$this->defaultPeriod = $default;
		$this->periodOptions = $options;
		return $this;
	}

	public function hasPeriods(): bool
	{
		return $this->defaultPeriod !== null;
	}

	/** @return list<WidgetPeriod> */
	public function getPeriodOptions(): array
	{
		return $this->periodOptions;
	}

	public function getDefaultPeriod(): ?WidgetPeriod
	{
		return $this->defaultPeriod;
	}

	/** Zvolené období, bez volby výchozí; widget bez období vrací null. */
	public function getPeriod(): ?WidgetPeriod
	{
		return $this->period ?? $this->defaultPeriod;
	}

	/** Hodnota z URL; neznámé nebo nenabízené období znamená výchozí. */
	public function selectPeriod(?string $value): static
	{
		$period = $this->defaultPeriod ? WidgetPeriod::fromUrl($value, $this->defaultPeriod) : null;
		$this->period = in_array($period, $this->periodOptions, true) ? $period : null;
		return $this;
	}
}
