<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/** Řada údajů v jedné kartě oddělených svislou linkou (nábor: nové, aktivní, exkluzivita, zrušené …). */
final class FiguresWidget extends Widget
{
	/** @var callable(?WidgetPeriod): iterable<mixed> */
	private $figures;

	/** @var list<WidgetFigure>|null */
	private ?array $resolvedFigures = null;

	/** @param callable(?WidgetPeriod): iterable<WidgetFigure> $figures */
	public function __construct(string $name, string $title, callable $figures)
	{
		parent::__construct($name, $title);
		$this->figures = $figures;
	}

	public function getType(): string
	{
		return 'figures';
	}

	/** @return list<WidgetFigure> */
	public function getFigures(): array
	{
		if ($this->resolvedFigures === null) {
			$this->resolvedFigures = [];
			foreach (($this->figures)($this->getPeriod()) as $figure) {
				if (!$figure instanceof WidgetFigure) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet údaje " . WidgetFigure::class . '.');
				}
				$this->resolvedFigures[] = $figure;
			}
		}
		return $this->resolvedFigures;
	}
}
