<?php

namespace ADT\FancyAdmin\Model\Menu;

class NavbarSubmenu
{
	protected ?string $title = null;

	/** @var NavbarSubmenuItem[] */
	protected array $subMenuItems = [];

	public function __construct(
		protected NavbarMenuItem $parent
	) {}

	public function addMenuItem(NavbarSubmenuItem $subMenuItems): self
	{
		$this->subMenuItems[] = $subMenuItems;
		return $this;
	}

	/**
	 * @return NavbarSubmenuItem[]
	 */
	public function getSubMenuItems(): array
	{
		return $this->subMenuItems;
	}

	public function getTitle(): ?string
	{
		return $this->title ?? $this->parent->getLabel();
	}

	public function setTitle(?string $title): self
	{
		$this->title = $title;
		return $this;
	}

}