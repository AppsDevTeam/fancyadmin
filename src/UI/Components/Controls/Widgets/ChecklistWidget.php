<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use UnexpectedValueException;

/**
 * Kompletace („Zakázka RD Beroun · kompletace“): pro každou položku název, body jako štítky ✓ / ✗ a pruh
 * splnění („Kompletace · 78 % – chybí foto a inzerce“, oranžově, kompletní zeleně). `setLimit(n)` = vidět
 * prvních n, zbytek pod „Zobrazit všech N“.
 */
final class ChecklistWidget extends Widget
{
	/** @var callable(?WidgetPeriod): iterable<WidgetChecklist> */
	private $items;

	/** @var list<WidgetChecklist>|null */
	private ?array $resolvedItems = null;

	private ?int $limit = null;

	/** @param callable(?WidgetPeriod): iterable<WidgetChecklist> $items */
	public function __construct(string $name, string $title, callable $items)
	{
		parent::__construct($name, $title);
		$this->items = $items;
	}

	public function getType(): string
	{
		return 'checklist';
	}

	/** @return list<WidgetChecklist> */
	public function getItems(): array
	{
		if ($this->resolvedItems === null) {
			$this->resolvedItems = [];
			foreach (($this->items)($this->getPeriod()) as $item) {
				if (!$item instanceof WidgetChecklist) {
					throw new UnexpectedValueException("Widget '{$this->getName()}' musí vracet položky " . WidgetChecklist::class . '.');
				}
				$this->resolvedItems[] = $item;
			}
		}
		return $this->resolvedItems;
	}

	public function setLimit(?int $limit): static
	{
		$this->limit = $limit !== null && $limit > 0 ? $limit : null;
		return $this;
	}

	public function getLimit(): ?int
	{
		return $this->limit;
	}
}
