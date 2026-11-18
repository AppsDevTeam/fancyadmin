<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/** Vlastní šablona projektu v kartě widgetu; dostane `$presenter`, `$widgetGrid` a předané parametry. */
final class TemplateWidget extends Widget
{
	/** @var array<string, mixed>|(\Closure(?WidgetPeriod): array<string, mixed>) */
	private array|\Closure $parameters;

	/** @param array<string, mixed>|(\Closure(?WidgetPeriod): array<string, mixed>) $parameters */
	public function __construct(string $name, ?string $title, private readonly string $file, array|\Closure $parameters = [])
	{
		parent::__construct($name, $title);
		$this->parameters = $parameters;
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
