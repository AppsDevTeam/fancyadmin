<?php

namespace ADT\FancyAdmin\Model\Menu;

class NavbarSubmenu
{
	protected ?string $title = null;

	/** @var array<NavbarSubmenuItem|NavbarSubmenuHeading> */
	protected array $subMenuItems = [];

	public function __construct(
		protected NavbarMenuItem $parent
	) {}

	public function addMenuItem(NavbarSubmenuItem $subMenuItems): self
	{
		$this->subMenuItems[] = $subMenuItems;
		return $this;
	}

	public function addHeading(string $label): self
	{
		$this->subMenuItems[] = new NavbarSubmenuHeading($label);
		return $this;
	}

	/**
	 * @return array<NavbarSubmenuItem|NavbarSubmenuHeading>
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