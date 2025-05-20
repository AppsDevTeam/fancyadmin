<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\UI\Component;

class NavbarMenuItem
{
	protected string $label = 'Test';
	protected string $faIcon = 'chart-simple';

	protected ?NavbarSubmenu $submenu = null;
	protected ?string $link = null;

	public function getLabel(): string
	{
		return $this->label;
	}

	public function setLabel(string $label): self
	{
		$this->label = $label;
		return $this;
	}

	public function getFaIcon(): string
	{
		return $this->faIcon;
	}

	public function setFaIcon(string $faIcon): self
	{
		$this->faIcon = $faIcon;
		return $this;
	}

	public function getSubmenu(): ?NavbarSubmenu
	{
		return $this->submenu;
	}

	private function setSubmenu(NavbarSubmenu $submenu): self
	{
		$this->submenu = $submenu;
		return $this;
	}

	public function getLink(): ?string
	{
		return $this->link;
	}

	public function setLink(string $link): self
	{
		$this->link = $link;
		return $this;
	}

	public function setupSubmenuItems(callable $setupSubmenuItem): self
	{
		$submenu = $this->submenu ?? new NavbarSubmenu($this);
		$setupSubmenuItem($submenu);
		$this->setSubmenu($submenu);
		return $this;
	}

	public function isCurrent(Component $presenter): bool {
		if ($this->getSubmenu()) {
			foreach ($this->getSubmenu()->getSubMenuItems() as $submenuItem) {
				if ($submenuItem->isCurrent($presenter)) {
					return true;
				}
			}
		}

		return $presenter->isLinkCurrent($this->getLink());
	}
}