<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use InvalidArgumentException;

/**
 * Data grafu widgetu `chart` pro builtin `Chart` z adt-js-components (Chart.js). Barvy doplní plugin
 * `fancyadminTheme` z CSS proměnných, dataset s vlastní barvou si ji nechá.
 */
final class WidgetChart
{
	public const TYPES = ['line', 'bar', 'doughnut', 'pie'];

	/**
	 * @param list<string|list<string>> $labels popisky osy (dny, produkty); pole = víc řádků popisku
	 * @param list<array<string, mixed>> $datasets `label`, `data` a případně další volby datasetu Chart.js
	 * @param array<string, mixed>|null $format volby `Intl.NumberFormat` pro osu hodnot a tooltip
	 * @param string|null $indexAxis `x` nebo `y` (vodorovné sloupce)
	 * @param array<string, mixed> $options další volby Chart.js, mají přednost
	 * @param array{int, int}|null $highlight zvýrazněné popisky osy x [od, do] včetně (indexy) - podbarvené pásmo,
	 *                                        např. zvolené období v grafu delšího vývoje
	 */
	public function __construct(
		public readonly string $type,
		public readonly array $labels,
		public readonly array $datasets,
		public readonly ?array $format = null,
		public readonly ?string $indexAxis = null,
		public readonly array $options = [],
		public readonly ?array $highlight = null,
	) {
		if (!in_array($type, self::TYPES, true)) {
			throw new InvalidArgumentException("Nepodporovaný typ grafu '$type', povolené: " . implode(', ', self::TYPES) . '.');
		}
		if ($indexAxis !== null && !in_array($indexAxis, ['x', 'y'], true)) {
			throw new InvalidArgumentException("indexAxis musí být 'x' nebo 'y'.");
		}
		if ($highlight !== null && (count($highlight) !== 2 || !is_int($highlight[0] ?? null) || !is_int($highlight[1] ?? null))) {
			throw new InvalidArgumentException('highlight je dvojice indexů popisků [od, do].');
		}
		foreach ($datasets as $_dataset) {
			if (!isset($_dataset['data']) || !is_array($_dataset['data'])) {
				throw new InvalidArgumentException('Dataset grafu musí mít pole data.');
			}
		}
	}

	public function isEmpty(): bool
	{
		return !$this->labels || !$this->datasets;
	}

	/** @return array<string, mixed> konfigurace pro `data-adt-chart` */
	public function toConfig(): array
	{
		$options = [
			'maintainAspectRatio' => false,
			'interaction' => ['mode' => 'index', 'intersect' => false],
		];
		if ($this->indexAxis !== null) {
			$options['indexAxis'] = $this->indexAxis;
		}
		if ($this->highlight !== null) {
			$options['plugins']['fancyadminTheme']['highlight'] = $this->highlight;
		}

		$config = [
			'type' => $this->type,
			'data' => ['labels' => $this->labels, 'datasets' => $this->datasets],
			'options' => array_replace_recursive($options, $this->options),
			'plugins' => ['fancyadminTheme'],
		];
		if ($this->format !== null) {
			$config['format'] = $this->format;
		}
		return $config;
	}
}
