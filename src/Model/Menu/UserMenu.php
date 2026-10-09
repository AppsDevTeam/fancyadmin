<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\LinkGenerator;

class UserMenu
{
	protected bool $addMyProfileMenuItem = true;

	protected bool $addFirebaseMenuItem = true;

	protected ?string $profileLink = null;

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

	public function setAddMyProfileMenuItem(bool $addMyProfileMenuItem): self
	{
		$this->addMyProfileMenuItem = $addMyProfileMenuItem;
		return $this;
	}

	public function isAddMyProfileMenuItem(): bool
	{
		return $this->addMyProfileMenuItem;
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