<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use Nette\HtmlStringable;

/**
 * Nadpis sekce nad kartami (bez karty): modré verzálky, za nadpisem šedý dovětek (setSubtitle), štítky,
 * vpravo odkaz (setLink s popiskem) a pod nadpisem volitelná vysvětlivka (setText, Html se neescapuje).
 * Zabírá celou šířku mřížky.
 */
final class SectionWidget extends Widget
{
	/** @var string|HtmlStringable|(\Closure(?WidgetPeriod): (string|HtmlStringable|null))|null */
	private string|HtmlStringable|\Closure|null $text = null;

	public function getType(): string
	{
		return 'section';
	}

	/** @param string|HtmlStringable|(\Closure(?WidgetPeriod): (string|HtmlStringable|null))|null $text */
	public function setText(string|HtmlStringable|\Closure|null $text): static
	{
		$this->text = $text;
		return $this;
	}

	public function getText(): string|HtmlStringable|null
	{
		return $this->text instanceof \Closure ? ($this->text)($this->getPeriod()) : $this->text;
	}
}
