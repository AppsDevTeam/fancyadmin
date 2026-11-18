<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Dashboard;

use InvalidArgumentException;

/** Widget dashboardu. Data dodává callable, které se zavolá až při vykreslení. */
abstract class DashboardWidget
{
	private ?string $description = null;

	private int $span = 1;

	private int $position = 0;

	public function __construct(
		private readonly string $name,
		private ?string $title = null,
	) {
		if (!preg_match('~^[a-zA-Z][a-zA-Z0-9_-]*$~', $name)) {
			throw new InvalidArgumentException("Neplatné jméno widgetu '$name': písmena, číslice, '-' a '_', na začátku písmeno.");
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
}
