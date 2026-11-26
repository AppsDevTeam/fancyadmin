<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

/** Tabulka; hodnota buňky se bere z řádku podle klíče sloupce, `Html` se nevyescapuje. */
final class TableWidget extends Widget
{
	/** @var array<string, WidgetTableColumn> */
	private array $columns = [];

	/** @var callable(?WidgetPeriod): iterable<array<string, mixed>> */
	private $rows;

	/** @var list<array<string, mixed>>|null */
	private ?array $resolvedRows = null;

	/** @var array<string, mixed>|(\Closure(?WidgetPeriod): ?array<string, mixed>)|null */
	private array|\Closure|null $total = null;

	/** @var (\Closure(array<string, mixed>): ?string)|null */
	private ?\Closure $rowLink = null;

	private bool $compact = false;

	/** @var (\Closure(array<string, mixed>): list<array<string, mixed>>)|null */
	private ?\Closure $rowChildren = null;

	/**
	 * @param array<string, string|WidgetTableColumn> $columns klíč v řádku => popisek nebo sloupec
	 * @param callable(?WidgetPeriod): iterable<array<string, mixed>> $rows
	 */
	public function __construct(string $name, string $title, array $columns, callable $rows)
	{
		parent::__construct($name, $title);
		foreach ($columns as $_key => $_column) {
			$this->columns[$_key] = $_column instanceof WidgetTableColumn ? $_column : new WidgetTableColumn($_column);
		}
		$this->rows = $rows;
	}

	public function getType(): string
	{
		return 'table';
	}

	/** @return array<string, WidgetTableColumn> */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/** @return list<array{label: ?string, span: int, tone: string}> skupiny sloupců zleva; prázdné, když žádný sloupec skupinu nemá */
	public function getColumnGroups(): array
	{
		$groups = [];
		foreach ($this->columns as $column) {
			$last = array_key_last($groups);
			if ($last !== null && $groups[$last]['label'] === $column->group) {
				$groups[$last]['span']++;
			} else {
				$groups[] = ['label' => $column->group, 'span' => 1, 'tone' => ($column->groupTone ?? WidgetTone::Primary)->value];
			}
		}
		return array_filter($groups, fn(array $group) => $group['label'] !== null) ? $groups : [];
	}

	/**
	 * Řádek „Celkem“ pod tabulkou (součty), hodnoty podle klíčů sloupců jako řádek.
	 *
	 * @param array<string, mixed>|(\Closure(?WidgetPeriod): ?array<string, mixed>)|null $total
	 */
	public function setTotal(array|\Closure|null $total): static
	{
		$this->total = $total;
		return $this;
	}

	/** @return array<string, mixed>|null */
	public function getTotal(): ?array
	{
		return $this->total instanceof \Closure ? ($this->total)($this->getPeriod()) : $this->total;
	}

	/**
	 * Proklik řádku (pobočka → její makléři): odkazem je první buňka a klik kamkoli do řádku.
	 *
	 * @param (\Closure(array<string, mixed>): ?string)|null $link
	 */
	public function setRowLink(?\Closure $link): static
	{
		$this->rowLink = $link;
		return $this;
	}

	/** @param array<string, mixed> $row */
	public function getRowLink(array $row): ?string
	{
		return $this->rowLink ? ($this->rowLink)($row) : null;
	}

	/** Užší okraje buněk - tabulka v půlce šířky mřížky se vejde bez vodorovného posuvníku. */
	public function setCompact(bool $compact = true): static
	{
		$this->compact = $compact;
		return $this;
	}

	public function isCompact(): bool
	{
		return $this->compact;
	}

	/** @return list<array<string, mixed>> */
	public function getRows(): array
	{
		if ($this->resolvedRows === null) {
			$this->resolvedRows = [];
			foreach (($this->rows)($this->getPeriod()) as $row) {
				$this->resolvedRows[] = $row;
			}
		}
		return $this->resolvedRows;
	}

	/**
	 * Vnořené řádky (seskupená tabulka - pobočka a pod ní její makléři): řádek s dětmi má šipku ▸ a po kliknutí
	 * je ukáže pod sebou odsazené. Stejné sloupce jako řádek.
	 *
	 * @param (\Closure(array<string, mixed>): list<array<string, mixed>>)|null $children
	 */
	public function setRowChildren(?\Closure $children): static
	{
		$this->rowChildren = $children;
		return $this;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return list<array<string, mixed>>
	 */
	public function getRowChildren(array $row): array
	{
		return $this->rowChildren ? array_values(($this->rowChildren)($row)) : [];
	}
}
