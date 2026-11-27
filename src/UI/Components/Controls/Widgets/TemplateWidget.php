<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Vlastní šablona projektu v kartě widgetu; dostane `$presenter`, `$widgetGrid` a předané parametry. Bez karty
 * (setPlain) se vykreslí jen šablona - např. přepínač mezi bloky stránky.
 */
final class TemplateWidget extends Widget
{
	/** @var array<string, mixed>|(\Closure(?WidgetPeriod): array<string, mixed>) */
	private array|\Closure $parameters;

	private bool $plain = false;

	/** @param array<string, mixed>|(\Closure(?WidgetPeriod): array<string, mixed>) $parameters */
	public function __construct(string $name, ?string $title, private readonly string $file, array|\Closure $parameters = [])
	{
		parent::__construct($name, $title);
		$this->parameters = $parameters;
	}

	/** Bez karty a hlavičky - na místě widgetu jen obsah šablony. */
	public function setPlain(bool $plain = true): static
	{
		$this->plain = $plain;
		return $this;
	}

	public function isPlain(): bool
	{
		return $this->plain;
	}

	public function getType(): string
	{
		return 'template';
	}

	public function getFile(): string
	{
		return $this->file;
	}

	/** @return array<string, mixed> */
	public function getParameters(): array
	{
		return $this->parameters instanceof \Closure ? ($this->parameters)($this->getPeriod()) : $this->parameters;
	}
}
