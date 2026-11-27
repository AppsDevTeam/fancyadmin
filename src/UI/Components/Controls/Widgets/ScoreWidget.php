<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/**
 * Hodnocení („Hodnocení pobočky“): karta s popisem a stavem vlevo a skóre v kruhu vpravo (nadpis widgetu
 * je popisek u kruhu, popis widgetu jeho bublina), pod ní karta se složkami hodnocení s pruhy. Bez
 * společné karty a hlavičky widgetu.
 */
final class ScoreWidget extends Widget
{
	/** @var callable(?WidgetPeriod): WidgetScore */
	private $score;

	private ?WidgetScore $resolvedScore = null;

	/** @param callable(?WidgetPeriod): WidgetScore $score */
	public function __construct(string $name, string $title, callable $score)
	{
		parent::__construct($name, $title);
		$this->score = $score;
	}

	public function getType(): string
	{
		return 'score';
	}

	public function getScore(): WidgetScore
	{
		if ($this->resolvedScore === null) {
			$score = ($this->score)($this->getPeriod());
			if (!$score instanceof WidgetScore) {
				throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet " . WidgetScore::class . '.');
			}
			$this->resolvedScore = $score;
		}
		return $this->resolvedScore;
	}
}
