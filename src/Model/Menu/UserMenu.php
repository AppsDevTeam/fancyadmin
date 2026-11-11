<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\LinkGenerator;

class UserMenu
{
	protected bool $addMyProfileMenuItem = true;

	protected bool $addMyAccountMenuItem = false;

	protected bool $addFirebaseMenuItem = true;

	protected ?string $profileLink = null;

	/** @var string[]|null */
	protected ?array $headerLines = null;

	/** @var UserMenuItem[] */
	protected array $menuItems = [];

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

	public function setFirebaseMenuItem(bool $value): self
	{
		$this->addFirebaseMenuItem = $value;
		return $this;
	}

	public function isFirebaseMenuItem(): bool
	{
		return $this->addFirebaseMenuItem;
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