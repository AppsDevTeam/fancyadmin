<?php

namespace ADT\FancyAdmin\Model\Menu;

/**
 * Výběr v uživatelském menu (např. pobočka, na které uživatel pracuje). Každá volba
 * je odkaz: `link` s `linkArgs` a vybranou hodnotou v parametru `parameter`. Změna výběru
 * odkaz otevře ajaxem (userMenuSelect.js), signál si hodnotu uloží a přesměruje.
 */
class UserMenuSelect
{
	protected string $label = '';

	protected string $faIcon = 'list';

	/** @var array<int|string, string> hodnota => popisek */
	protected array $options = [];

	protected int|string|null $selected = null;

	protected ?string $prompt = null;

	protected string $link = 'this';

	protected array $linkArgs = [];

	protected string $parameter = 'value';

	/** Popisek nad výběrem - prochází překladačem jako popisky položek menu */
	public function setLabel(string $label): self
	{
		$this->label = $label;
		return $this;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function setFaIcon(string $faIcon): self
	{
		$this->faIcon = $faIcon;
		return $this;
	}

	public function getFaIcon(): string
	{
		return $this->faIcon;
	}

	/**
	 * Volby výběru. Popisky se vypisují tak, jak jsou (bez překladu) - typicky názvy záznamů.
	 *
	 * @param array<int|string, string> $options hodnota => popisek
	 */
	public function setOptions(array $options): self
	{
		$this->options = $options;
		return $this;
	}

	/** @return array<int|string, string> */
	public function getOptions(): array
	{
		return $this->options;
	}

	public function setSelected(int|string|null $selected): self
	{
		$this->selected = $selected;
		return $this;
	}

	public function getSelected(): int|string|null
	{
		return $this->selected;
	}

	/**
	 * Prázdná volba nahoře, když není nic vybrané - jinak by select ukázal první volbu,
	 * jako by byla vybraná, a její výběr by nevyvolal změnu. Prochází překladačem.
	 */
	public function setPrompt(?string $prompt): self
	{
		$this->prompt = $prompt;
		return $this;
	}

	public function getPrompt(): ?string
	{
		return $this->selected === null || !array_key_exists($this->selected, $this->options) ? $this->prompt : null;
	}

	public function isSelected(int|string $value): bool
	{
		return $this->selected !== null && (string) $this->selected === (string) $value;
	}

	/**
	 * Kam změna výběru vede - typicky signál (`selectBranch!`), vybraná hodnota jde
	 * v parametru `$parameter`. Parametr se nesmí jmenovat jako parametr routy (`id`),
	 * odkaz by pak vedl na jinou stránku.
	 */
	public function setLink(string $link, string $parameter, array $linkArgs = []): self
	{
		$this->link = $link;
		$this->parameter = $parameter;
		$this->linkArgs = $linkArgs;
		return $this;
	}

	public function getLink(): string
	{
		return $this->link;
	}

	/** Argumenty odkazu volby s hodnotou `$value` */
	public function getLinkArgsFor(int|string $value): array
	{
		return [$this->parameter => $value] + $this->linkArgs;
	}
}
