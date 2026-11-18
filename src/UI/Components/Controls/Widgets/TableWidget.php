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
}
