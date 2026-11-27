<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use Nette\HtmlStringable;

/**
 * Nadpis sekce nad kartami (bez karty): modré verzálky, za nadpisem šedý dovětek (setSubtitle), štítky,
 * vpravo odkaz (setLink s popiskem) a pod nadpisem volitelná vysvětlivka (setText, Html se neescapuje).
 * Zabírá celou šířku mřížky. Hlavní sekce (setMajor) má větší nadpis podtržený primární barvou a šedý dovětek
 * vedle („VÝKONNOST realizované provize a provize v rezervacích“) - člení dlouhou stránku na bloky.
 * Nadpis v barvě tónu (setTone) - např. oranžové „OTEVŘENÉ REZERVACE · ZATÍM NENÍ TRŽBA“; bez tónu primární barva.
 */
final class SectionWidget extends Widget
{
	/** @var string|HtmlStringable|(\Closure(?WidgetPeriod): (string|HtmlStringable|null))|null */
	private string|HtmlStringable|\Closure|null $text = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $suffix = null;

	private bool $major = false;

	private ?WidgetTone $tone = null;

	/** Dovětek ve stylu nadpisu („NOVĚ ZADANÉ NABÍDKY · ŘÍJEN 2026“), text nebo Closure s obdobím. */
	public function setSuffix(string|\Closure|null $suffix): static
	{
		$this->suffix = $suffix;
		return $this;
	}

	public function getSuffix(): ?string
	{
		return $this->suffix instanceof \Closure ? ($this->suffix)($this->getPeriod()) : $this->suffix;
	}

	/** Hlavní sekce stránky: větší nadpis s linkou pod ním (bloky pod sebou), dovětek setSubtitle vedle nadpisu. */
	public function setMajor(bool $major = true): static
	{
		$this->major = $major;
		return $this;
	}

	public function isMajor(): bool
	{
		return $this->major;
	}

	/** Nadpis v barvě tónu (tmavší text tónu, --fa-widgets-{tone}-text); null = primární barva. */
	public function setTone(?WidgetTone $tone): static
	{
		$this->tone = $tone;
		return $this;
	}

	public function getTone(): ?WidgetTone
	{
		return $this->tone;
	}

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
