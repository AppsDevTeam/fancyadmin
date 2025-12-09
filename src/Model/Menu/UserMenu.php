<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\LinkGenerator;

class UserMenu
{
	protected bool $addMyAccountMenuItem = true;

	/** @var UserMenuItem[] */
	protected array $menuItems = [];

	protected LinkGenerator $linkGenerator;

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

	public function setAddMyAccountMenuItem(bool $addMyAccountMenuItem): self
	{
		$this->addMyAccountMenuItem = $addMyAccountMenuItem;
		return $this;
	}

	public function isAddMyAccountMenuItem(): bool
	{
		return $this->addMyAccountMenuItem;
	}
}