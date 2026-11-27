<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/**
 * Položka widgetu `checklist` („Kompletace zakázky“): název (s volitelným odkazem), body jako štítky
 * (`popisek => true` ✓ zeleně, `false` ✗ červeně, `null` šedě - nezjišťuje se) a pod nimi pruh splnění
 * z bodů true / false s textem „Kompletace · 78 % – chybí foto a inzerce“.
 */
final class WidgetChecklist
{
	/** @param array<string, ?bool> $items */
	public function __construct(
		public readonly string $title,
		public readonly array $items,
		public readonly ?string $link = null,
	) {
	}

	/** Podíl splněných bodů (bez šedých); bez hodnocených bodů null. */
	public function getRatio(): ?float
	{
		$rated = array_filter($this->items, fn(?bool $done) => $done !== null);
		return $rated ? count(array_filter($rated)) / count($rated) : null;
	}

	/** @return list<string> popisky nesplněných bodů */
	public function getMissing(): array
	{
		return array_map('strval', array_keys(array_filter($this->items, fn(?bool $done) => $done === false)));
	}

	/** Nesplněné body do věty („smlouva, PENB, inzerce“): malým písmem, zkratky z verzálek zůstanou. */
	public function getMissingText(): string
	{
		return implode(', ', array_map(fn(string $label) => mb_strtoupper($label) === $label ? $label : mb_strtolower($label), $this->getMissing()));
	}
}
