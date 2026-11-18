<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

/** Jedno velké číslo s poznámkou (např. objednávky čekající na odeslání). */
final class StatWidget extends DashboardWidget
{
	/** @var callable(): (int|float|string) */
	private $value;

	private bool $resolved = false;

	private int|float|string $resolvedValue = '';

	/** @var string|callable(): ?string|null */
	private $note = null;

	private ?string $icon = null;

	private ?string $link = null;

	private DashboardTone $tone = DashboardTone::Neutral;

	/** @param callable(): (int|float|string) $value */
	public function __construct(string $name, string $title, callable $value)
	{
		parent::__construct($name, $title);
		$this->value = $value;
	}

	public function getType(): string
	{
		return 'stat';
	}

	public function getValue(): int|float|string
	{
		if (!$this->resolved) {
			$this->resolvedValue = ($this->value)();
			$this->resolved = true;
		}
		return $this->resolvedValue;
	}

	/** @param string|callable(): ?string|null $note */
	public function setNote(string|callable|null $note): static
	{
		$this->note = $note;
		return $this;
	}

	public function getNote(): ?string
	{
		return is_callable($this->note) ? ($this->note)() : $this->note;
	}

	public function setIcon(?string $icon): static
	{
		$this->icon = $icon;
		return $this;
	}

	public function getIcon(): ?string
	{
		return $this->icon;
	}

	public function setLink(?string $link): static
	{
		$this->link = $link;
		return $this;
	}

	public function getLink(): ?string
	{
		return $this->link;
	}

	public function setTone(DashboardTone $tone): static
	{
		$this->tone = $tone;
		return $this;
	}

	public function getTone(): DashboardTone
	{
		return $this->tone;
	}
}
