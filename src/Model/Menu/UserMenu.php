<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\LinkGenerator;

class UserMenu
{
	protected bool $addMyProfileMenuItem = true;

	protected bool $addMyAccountMenuItem = false;


	protected ?string $profileLink = null;

	/** @var string[]|null */
	protected ?array $headerLines = null;

	protected ?string $buttonNote = null;

	/** @var UserMenuItem[] */
	protected array $menuItems = [];

	/** @var UserMenuSelect[] */
	protected array $selects = [];

	protected LinkGenerator $linkGenerator;

	/**
	 * Řádky v hlavičce menu vedle avataru - první je zvýrazněný titulek, ostatní pod ním.
	 * Texty se vypisují tak, jak jsou (bez překladu). Null = výchozí jméno a e-mail uživatele.
	 *
	 * @param string[]|null $headerLines
	 */
	public function setHeaderLines(?array $headerLines): self
	{
		$this->headerLines = $headerLines !== null ? array_values($headerLines) : null;
		return $this;
	}

	/**
	 * @return string[]|null
	 */
	public function getHeaderLines(): ?array
	{
		return $this->headerLines;
	}

	/**
	 * Krátký údaj u uživatele dole v menu (např. aktuální pobočka) - v rozbaleném menu pod
	 * jménem a rolí, ve sbaleném pod avatarem. Vypisuje se tak, jak je (bez překladu).
	 */
	public function setButtonNote(?string $buttonNote): self
	{
		$this->buttonNote = $buttonNote;
		return $this;
	}

	public function getButtonNote(): ?string
	{
		return $this->buttonNote;
	}

	public function addMenuItem(UserMenuItem $menuItem): self
	{
		$this->menuItems[] = $menuItem;
		return $this;
	}

	/**
	 * @return UserMenuItem[]
	 */
	public function getMenuItems(): array
	{
		return $this->menuItems;
	}

	/** Výběr pod položkami menu (nad přepínačem tmavého režimu) */
	public function addSelect(UserMenuSelect $select): self
	{
		$this->selects[] = $select;
		return $this;
	}

	/**
	 * @return UserMenuSelect[]
	 */
	public function getSelects(): array
	{
		return $this->selects;
	}


	public function setLinkGenerator(LinkGenerator $linkGenerator): self
	{
		$this->linkGenerator = $linkGenerator;
		return $this;
	}

	public function getLinkGenerator(): LinkGenerator
	{
		return $this->linkGenerator;
	}

	public function isLinkCurrent(?string $destination = null, $args = []): bool
	{
		if ($destination !== null) {
			$args = func_num_args() < 3 && is_array($args)
				? $args
				: array_slice(func_get_args(), 1);
			$this->linkGenerator->createRequest($this, $destination, $args, 'test');
		}

		return (bool)$this->linkGenerator->lastRequest?->hasFlag('current');
	}

	public function setAddMyProfileMenuItem(bool $addMyProfileMenuItem): self
	{
		$this->addMyProfileMenuItem = $addMyProfileMenuItem;
		return $this;
	}

	public function isAddMyProfileMenuItem(): bool
	{
		return $this->addMyProfileMenuItem;
	}

	/**
	 * Odkaz na stránku Můj účet (presenter Account v modulu, viz AccountPresenterTrait).
	 * Zobrazí se jen uživateli s vybraným účtem a právem na resource `<modul>.account`.
	 */
	public function setAddMyAccountMenuItem(bool $addMyAccountMenuItem): self
	{
		$this->addMyAccountMenuItem = $addMyAccountMenuItem;
		return $this;
	}

	public function isAddMyAccountMenuItem(): bool
	{
		return $this->addMyAccountMenuItem;
	}

	public function setProfileLink(string $profileLink): self
	{
		$this->profileLink = $profileLink;
		return $this;
	}

	public function getProfileLink(): ?string
	{
		return $this->profileLink;
	}
}