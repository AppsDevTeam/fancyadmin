<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use InvalidArgumentException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Control;

/**
 * Mřížka widgetů (docs/widgets.md) - kdekoli v administraci, víc instancí na stránce. Rozložení skládá
 * projekt v kódu (přehled přes trait `Dashboard` presenteru), data widgetů se načtou až při vykreslení.
 */
class WidgetGridControl extends Control
{
	public const MAX_COLUMNS = 12;

	/** Období mřížky (setPeriods()) - platí pro widgety bez vlastního výběru. */
	#[Persistent]
	public ?string $period = null;

	private int $columns = 3;

	/** @var array<string, Widget> */
	private array $widgets = [];

	private ?WidgetPeriod $defaultPeriod = null;

	/** @var list<WidgetPeriod> */
	private array $periodOptions = [];

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $headerTitle = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $headerText = null;

	private ?string $headerImage = null;

	private bool $periodRendered = false;

	private ?string $pageTitle = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $pageSubtitle = null;

	/** @var list<array{label: string, link: ?string}> */
	private array $breadcrumbs = [];

	public function setColumns(int $columns): static
	{
		if ($columns < 1 || $columns > self::MAX_COLUMNS) {
			throw new InvalidArgumentException('Mřížka widgetů má 1 až ' . self::MAX_COLUMNS . ' sloupců.');
		}
		$this->columns = $columns;
		return $this;
	}

	public function getColumns(): int
	{
		return $this->columns;
	}

	/**
	 * Společné období mřížky: výběr v hlavičce mřížky, platí pro všechny widgety bez vlastního výběru
	 * (Widget::setPeriods()). Změna načte stránku znovu - mění se tím většina widgetů.
	 *
	 * @param list<WidgetPeriod>|null $options nabízená období, výchozí všechna
	 */
	public function setPeriods(WidgetPeriod $default, ?array $options = null): static
	{
		$options ??= WidgetPeriod::cases();
		if (!in_array($default, $options, true)) {
			throw new InvalidArgumentException('Výchozí období musí být mezi nabízenými.');
		}
		$this->defaultPeriod = $default;
		$this->periodOptions = $options;
		return $this;
	}

	public function hasPeriods(): bool
	{
		return $this->defaultPeriod !== null;
	}

	/** @return list<WidgetPeriod> */
	public function getPeriodOptions(): array
	{
		return $this->periodOptions;
	}

	/** Zvolené období mřížky; neznámé nebo nenabízené znamená výchozí, bez setPeriods() null. */
	public function getPeriod(): ?WidgetPeriod
	{
		if ($this->defaultPeriod === null) {
			return null;
		}
		$period = WidgetPeriod::fromUrl($this->period, $this->defaultPeriod);
		return in_array($period, $this->periodOptions, true) ? $period : $this->defaultPeriod;
	}

	/** @param array<string, mixed> $params */
	public function saveState(array &$params): void
	{
		if (!array_key_exists('period', $params)) {
			// Výchozí nebo neplatné období do odkazů nepatří
			$period = $this->getPeriod();
			$params['period'] = $period !== $this->defaultPeriod ? $period?->value : null;
		}
		parent::saveState($params);
	}

	/** Jméno pole s obdobím mřížky v URL (`dashboard-period`). */
	public function getPeriodParameterName(): string
	{
		return $this->getParameterId('period');
	}

	/**
	 * Hlavička mřížky (pás nad widgety): nadpis, text a výběr období mřížky.
	 *
	 * @param string|(\Closure(?WidgetPeriod): ?string) $title
	 * @param string|(\Closure(?WidgetPeriod): ?string)|null $text
	 */
	public function setHeader(string|\Closure $title, string|\Closure|null $text = null): static
	{
		$this->headerTitle = $title;
		$this->headerText = $text;
		return $this;
	}

	public function getHeaderTitle(): ?string
	{
		return $this->headerTitle instanceof \Closure ? ($this->headerTitle)($this->getPeriod()) : $this->headerTitle;
	}

	public function getHeaderText(): ?string
	{
		return $this->headerText instanceof \Closure ? ($this->headerText)($this->getPeriod()) : $this->headerText;
	}

	/**
	 * Obrázek v pásu hlavičky (fotka, panorama): vpravo, zleva ho překryje primární barva a dolů pás
	 * měkce přejde do pozadí stránky za první řadou widgetů.
	 */
	public function setHeaderImage(?string $url): static
	{
		$this->headerImage = $url;
		return $this;
	}

	public function getHeaderImage(): ?string
	{
		return $this->headerImage;
	}

	private bool $headerFullWidth = false;

	/**
	 * Pás hlavičky přes celou šířku stránky (k okrajům obsahové části, bez zaoblení), nadpis a období zůstávají
	 * zarovnané se sloupcem widgetů. Bez volby je pás široký jako mřížka.
	 */
	public function setHeaderFullWidth(bool $fullWidth = true): static
	{
		$this->headerFullWidth = $fullWidth;
		return $this;
	}

	public function isHeaderFullWidth(): bool
	{
		return $this->headerFullWidth;
	}

	public function hasHeader(): bool
	{
		return $this->headerTitle !== null || ($this->hasPeriods() && !$this->periodRendered);
	}

	/** Nadpis sekce nad kartami (SectionWidget) přes celou šířku mřížky. */
	public function addSection(string $name, string $title): SectionWidget
	{
		return $this->addWidget(new SectionWidget($name, $title))->setSpan(self::MAX_COLUMNS);
	}

	/** @param callable(?WidgetPeriod): (int|float|string) $value */
	public function addStat(string $name, string $title, callable $value): StatWidget
	{
		return $this->addWidget(new StatWidget($name, $title, $value));
	}

	/** @param callable(?WidgetPeriod): iterable<WidgetTile> $tiles */
	public function addTiles(string $name, callable $tiles, ?string $title = null): TilesWidget
	{
		return $this->addWidget(new TilesWidget($name, $tiles, $title));
	}

	/**
	 * @param array<string, string|WidgetTableColumn> $columns
	 * @param callable(?WidgetPeriod): iterable<array<string, mixed>> $rows
	 */
	public function addTable(string $name, string $title, array $columns, callable $rows): TableWidget
	{
		return $this->addWidget(new TableWidget($name, $title, $columns, $rows));
	}

	/** @param callable(?WidgetPeriod): (int|float|string|null) $value */
	public function addKpi(string $name, string $title, callable $value): KpiWidget
	{
		return $this->addWidget(new KpiWidget($name, $title, value: $value));
	}

	/** @param callable(?WidgetPeriod): iterable<WidgetFigure> $parts */
	public function addKpiParts(string $name, string $title, callable $parts): KpiWidget
	{
		return $this->addWidget(new KpiWidget($name, $title, parts: $parts));
	}

	/** @param callable(?WidgetPeriod): iterable<WidgetFigure> $figures */
	public function addFigures(string $name, string $title, callable $figures): FiguresWidget
	{
		return $this->addWidget(new FiguresWidget($name, $title, $figures));
	}

	/** @param callable(?WidgetPeriod): iterable<WidgetAlert> $alerts */
	public function addAlerts(string $name, string $title, callable $alerts): AlertsWidget
	{
		return $this->addWidget(new AlertsWidget($name, $title, $alerts));
	}

	/** @param callable(?WidgetPeriod): WidgetChart $chart */
	public function addChart(string $name, string $title, callable $chart): ChartWidget
	{
		return $this->addWidget(new ChartWidget($name, $title, $chart));
	}

	/** @param array<string, mixed>|(\Closure(?WidgetPeriod): array<string, mixed>) $parameters */
	public function addTemplate(string $name, ?string $title, string $file, array|\Closure $parameters = []): TemplateWidget
	{
		return $this->addWidget(new TemplateWidget($name, $title, $file, $parameters));
	}

	/**
	 * @template T of Widget
	 * @param T $widget
	 * @return T
	 */
	public function addWidget(Widget $widget): Widget
	{
		if (isset($this->widgets[$widget->getName()])) {
			throw new InvalidArgumentException("Widget '{$widget->getName()}' už v mřížce je.");
		}
		$this->widgets[$widget->getName()] = $widget;
		$widget->setPeriodFallback($this->getPeriod(...));
		$this->addComponent(new WidgetControl($widget), $widget->getName());
		return $widget;
	}

	public function getWidget(string $name): Widget
	{
		return $this->widgets[$name] ?? throw new InvalidArgumentException("Widget '$name' v mřížce není.");
	}

	/** @return list<Widget> seřazené podle pozice, se stejnou pozicí v pořadí přidání */
	public function getWidgets(): array
	{
		$widgets = array_values($this->widgets);
		usort($widgets, fn(Widget $a, Widget $b): int => $a->getPosition() <=> $b->getPosition());
		return $widgets;
	}

	public function getSpan(Widget $widget): int
	{
		return min($widget->getSpan(), $this->columns);
	}

	public function render(): void
	{
		$this->template->widgetGrid = $this;
		$this->template->setFile(__DIR__ . '/WidgetGridControl.latte');
		$this->template->render();
	}

	/**
	 * Jen výběr období (`{control dashboard:period}`) - do hlavičky stránky podstránky dashboardu. Mřížka
	 * vykreslená potom ho už nemá; bez nadpisu (setHeader) pak nemá ani pás hlavičky.
	 */
	public function renderPeriod(): void
	{
		$this->periodRendered = true;
		$this->template->widgetGrid = $this;
		$this->template->setFile(__DIR__ . '/WidgetGridPeriod.latte');
		$this->template->render();
	}

	/**
	 * Hlavička podstránky dashboardu (`{control dashboard:page}`): drobečková navigace, ve stejném řádku
	 * vpravo výběr období, pod ní nadpis a šedý podtitul. Poslední drobeček je aktuální stránka (bez odkazu).
	 *
	 * @param string|(\Closure(?WidgetPeriod): ?string)|null $subtitle podtitul (období, rozsah dat)
	 * @param array<string, ?string> $breadcrumbs popisek => odkaz (null u aktuální stránky)
	 */
	public function setPage(string $title, string|\Closure|null $subtitle = null, array $breadcrumbs = []): static
	{
		$this->pageTitle = $title;
		$this->pageSubtitle = $subtitle;
		$this->breadcrumbs = [];
		foreach ($breadcrumbs as $label => $link) {
			$this->breadcrumbs[] = ['label' => (string) $label, 'link' => $link];
		}
		return $this;
	}

	public function getPageTitle(): ?string
	{
		return $this->pageTitle;
	}

	public function getPageSubtitle(): ?string
	{
		return $this->pageSubtitle instanceof \Closure ? ($this->pageSubtitle)($this->getPeriod()) : $this->pageSubtitle;
	}

	/** @return list<array{label: string, link: ?string}> */
	public function getBreadcrumbs(): array
	{
		return $this->breadcrumbs;
	}

	/** Hlavička podstránky (setPage) včetně výběru období - mřížka vykreslená potom ho už nemá. */
	public function renderPage(): void
	{
		$this->periodRendered = $this->hasPeriods();
		$this->template->widgetGrid = $this;
		$this->template->setFile(__DIR__ . '/WidgetGridPage.latte');
		$this->template->render();
	}

	public function isPeriodRendered(): bool
	{
		return $this->periodRendered;
	}
}
