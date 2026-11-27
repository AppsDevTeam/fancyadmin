<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Controls\Widgets;

use InvalidArgumentException;

/** Widget mřížky. Data dodává callable, které se zavolá až při vykreslení. */
abstract class Widget
{
	private ?string $description = null;

	private int $span = 1;

	private int $position = 0;

	private ?WidgetPeriod $defaultPeriod = null;

	/** @var list<WidgetPeriod> */
	private array $periodOptions = [];

	private ?string $requestedPeriod = null;

	/** @var (\Closure(): ?WidgetPeriod)|null období mřížky pro widget bez vlastního výběru */
	private ?\Closure $periodFallback = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $subtitle = null;

	/** @var list<WidgetBadge>|(\Closure(?WidgetPeriod): list<WidgetBadge>) */
	private array|\Closure $badges = [];

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $link = null;

	private ?string $linkLabel = null;

	private ?string $unavailable = null;

	private ?string $emptyText = null;

	private bool $collapsible = false;

	/** @var array{target: string, open: string, close: string}|null */
	private ?array $toggle = null;

	private ?string $panelGroup = null;

	private ?string $titleStyle = null;

	/** @var string|(\Closure(?WidgetPeriod): ?string)|null */
	private string|\Closure|null $aside = null;

	/** @var array<string, array{options: array<string, string>, default: string}> */
	private array $switches = [];

	/** @var array<string, string> volby z URL */
	private array $requestedSwitches = [];

	public function __construct(
		private readonly string $name,
		private ?string $title = null,
	) {
		// Jméno je zároveň jméno podkomponenty (persistentní parametr, snippet)
		if (!preg_match('~^[a-zA-Z][a-zA-Z0-9_]*$~', $name)) {
			throw new InvalidArgumentException("Neplatné jméno widgetu '$name': písmena, číslice a '_', na začátku písmeno.");
		}
	}

	abstract public function getType(): string;

	public function getName(): string
	{
		return $this->name;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setTitle(?string $title): static
	{
		$this->title = $title;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): static
	{
		$this->description = $description;
		return $this;
	}

	/** @param string|(\Closure(?WidgetPeriod): ?string)|null $subtitle šedý dovětek za nadpisem (období, stav k datu) */
	public function setSubtitle(string|\Closure|null $subtitle): static
	{
		$this->subtitle = $subtitle;
		return $this;
	}

	public function getSubtitle(): ?string
	{
		return $this->subtitle instanceof \Closure ? ($this->subtitle)($this->getPeriod()) : $this->subtitle;
	}

	/** @param list<WidgetBadge>|(\Closure(?WidgetPeriod): list<WidgetBadge>) $badges štítky (počet, změna, stav) */
	public function setBadges(array|\Closure $badges): static
	{
		$this->badges = $badges;
		return $this;
	}

	/** @return list<WidgetBadge> */
	public function getBadges(): array
	{
		$badges = $this->badges instanceof \Closure ? ($this->badges)($this->getPeriod()) : $this->badges;
		foreach ($badges as $_badge) {
			if (!$_badge instanceof WidgetBadge) {
				throw new \UnexpectedValueException("Štítky widgetu '{$this->name}' musí být " . WidgetBadge::class . '.');
			}
		}
		return array_values($badges);
	}

	/**
	 * Proklik na podrobnosti. Bez popisku je odkazem celý widget (šipka vpravo v hlavičce),
	 * s popiskem je to odkaz v patičce widgetu („Všechna upozornění ›“). `Closure` dostane období widgetu,
	 * proklik tak může zvolené období předat dál (stránka podrobností otevřená na stejném období).
	 *
	 * @param string|(\Closure(?WidgetPeriod): ?string)|null $link
	 */
	public function setLink(string|\Closure|null $link, ?string $label = null): static
	{
		$this->link = $link;
		$this->linkLabel = $label;
		return $this;
	}

	public function getLink(): ?string
	{
		return $this->link instanceof \Closure ? ($this->link)($this->getPeriod()) : $this->link;
	}

	public function getLinkLabel(): ?string
	{
		return $this->linkLabel;
	}

	/**
	 * Widget, pro který zatím nejsou data: místo obsahu ukáže hlášku `fcadmin.widgets.unavailable`
	 * a důvod; data widgetu se nevolají. Rozložení stránky tak drží místo i pro ukazatele, které teprve přibudou.
	 */
	public function setUnavailable(?string $reason): static
	{
		$this->unavailable = $reason;
		return $this;
	}

	public function isUnavailable(): bool
	{
		return $this->unavailable !== null;
	}

	public function getUnavailableReason(): ?string
	{
		return $this->unavailable;
	}

	/** Text widgetu bez dat místo výchozího „Za zvolené období nejsou žádná data.“ (např. stav k dnešku). */
	public function setEmptyText(?string $text): static
	{
		$this->emptyText = $text;
		return $this;
	}

	public function getEmptyText(): ?string
	{
		return $this->emptyText;
	}

	/** Sbalitelný widget: vidět je jen hlavička, obsah se rozbalí kliknutím (`<details>`). */
	public function setCollapsible(bool $collapsible = true): static
	{
		$this->collapsible = $collapsible;
		return $this;
	}

	public function isCollapsible(): bool
	{
		return $this->collapsible;
	}

	public function getSpan(): int
	{
		return $this->span;
	}

	/** Počet sloupců mřížky, které widget zabere (víc než má mřížka se ořízne). */
	public function setSpan(int $span): static
	{
		if ($span < 1) {
			throw new InvalidArgumentException('Widget musí zabírat aspoň jeden sloupec.');
		}
		$this->span = $span;
		return $this;
	}

	public function getPosition(): int
	{
		return $this->position;
	}

	/** Pořadí v mřížce; stejná hodnota zachová pořadí přidání. */
	public function setPosition(int $position): static
	{
		$this->position = $position;
		return $this;
	}

	/**
	 * Výběr období v hlavičce widgetu; data widgetu pak dostanou zvolené období jako parametr.
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

	public function getDefaultPeriod(): ?WidgetPeriod
	{
		return $this->defaultPeriod;
	}

	/**
	 * Zvolené období, bez volby výchozí; neznámé nebo nenabízené znamená výchozí. Widget bez vlastního výběru
	 * dostane období mřížky (WidgetGridControl::setPeriods()), bez něj null.
	 */
	public function getPeriod(): ?WidgetPeriod
	{
		if ($this->defaultPeriod === null) {
			return $this->periodFallback !== null ? ($this->periodFallback)() : null;
		}
		$period = WidgetPeriod::fromUrl($this->requestedPeriod, $this->defaultPeriod);
		return in_array($period, $this->periodOptions, true) ? $period : $this->defaultPeriod;
	}

	/**
	 * Hodnota z URL. Vyhodnotí se až v getPeriod(): stav se načte při připojení k presenteru a widget přidaný
	 * do už připojené mřížky dostane nabídku období (setPeriods) až potom.
	 */
	public function selectPeriod(?string $value): static
	{
		$this->requestedPeriod = $value;
		return $this;
	}

	/** @param (\Closure(): ?WidgetPeriod)|null $fallback období mřížky; nastavuje ho WidgetGridControl */
	public function setPeriodFallback(?\Closure $fallback): static
	{
		$this->periodFallback = $fallback;
		return $this;
	}

	/**
	 * Odkaz dole v kartě, který otevře / zavře panel jiného widgetu (setPanel) - „Seznam nabídek ▾“ /
	 * „Skrýt seznam ▴“. Karta s otevřeným panelem má zvýrazněný okraj.
	 */
	public function setToggle(string $target, string $openLabel, string $closeLabel): static
	{
		$this->toggle = ['target' => $target, 'open' => $openLabel, 'close' => $closeLabel];
		return $this;
	}

	/** @return array{target: string, open: string, close: string}|null */
	public function getToggle(): ?array
	{
		return $this->toggle;
	}

	/**
	 * Widget je panel, který otevírá odkaz jiné karty (setToggle) - do té doby skrytý; ve skupině je otevřený
	 * nejvýš jeden. V hlavičce panelu je „Zavřít seznam“.
	 */
	public function setPanel(string $group = 'default'): static
	{
		$this->panelGroup = $group;
		return $this;
	}

	public function getPanelGroup(): ?string
	{
		return $this->panelGroup;
	}

	/**
	 * Šedý doplněk vpravo v hlavičce karty (např. „91 % z realizovaných provizí“) - na rozdíl od podtitulu
	 * není za nadpisem ve stylu nadpisu. Text nebo Closure s obdobím.
	 */
	public function setAside(string|\Closure|null $aside): static
	{
		$this->aside = $aside;
		return $this;
	}

	public function getAside(): ?string
	{
		return $this->aside instanceof \Closure ? ($this->aside)($this->getPeriod()) : $this->aside;
	}

	/** Styl nadpisu karty: `section` = jako nadpis sekce (modré verzálky), jinak výchozí nadpis widgetu. */
	public function setTitleStyle(?string $style): static
	{
		$this->titleStyle = $style;
		return $this;
	}

	public function getTitleStyle(): ?string
	{
		return $this->titleStyle;
	}

	/**
	 * Přepínač v hlavičce widgetu (skupina tlačítek, např. jednotka „ks / Kč“ nebo rozsah „12 měsíců / 3 roky /
	 * Vše“). Zvolená hodnota je v URL (`<mřížka>-<widget>-switch[<jméno>]`, výchozí se nepíše), přepnutí
	 * překreslí jen widget; data si ji přečtou přes getSwitch().
	 *
	 * @param array<string, string> $options hodnota => popisek
	 */
	public function addSwitch(string $name, array $options, ?string $default = null): static
	{
		if (!$options) {
			throw new InvalidArgumentException("Přepínač '$name' widgetu '{$this->name}' nemá žádnou volbu.");
		}
		$default ??= (string) array_key_first($options);
		if (!array_key_exists($default, $options)) {
			throw new InvalidArgumentException("Výchozí volba '$default' přepínače '$name' widgetu '{$this->name}' není mezi volbami.");
		}
		$this->switches[$name] = ['options' => array_map('strval', $options), 'default' => $default];
		return $this;
	}

	/** Zvolená hodnota přepínače; neznámá nebo chybějící znamená výchozí. */
	public function getSwitch(string $name): string
	{
		if (!isset($this->switches[$name])) {
			throw new InvalidArgumentException("Widget '{$this->name}' nemá přepínač '$name'.");
		}
		$value = $this->requestedSwitches[$name] ?? null;
		return $value !== null && array_key_exists($value, $this->switches[$name]['options']) ? $value : $this->switches[$name]['default'];
	}

	/** @return array<string, array{options: array<string, string>, default: string}> */
	public function getSwitches(): array
	{
		return $this->switches;
	}

	/**
	 * Volby z URL. Vyhodnotí se až v getSwitch() - přepínač může přibýt i po načtení stavu.
	 *
	 * @param array<mixed> $values
	 */
	public function selectSwitches(array $values): static
	{
		$this->requestedSwitches = array_map('strval', array_filter($values, 'is_scalar'));
		return $this;
	}

	/** @return array<string, string> zvolené hodnoty, které nejsou výchozí (do odkazů) */
	public function getSwitchState(): array
	{
		$state = [];
		foreach ($this->switches as $name => $switch) {
			if (($value = $this->getSwitch($name)) !== $switch['default']) {
				$state[$name] = $value;
			}
		}
		return $state;
	}
}
