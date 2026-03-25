<?php

namespace ADT\FancyAdmin\Model\Menu;

use ADT\FancyAdmin\Model\Security\SecurityUser;
use Nette\Application\UI\Component;
use Nette\Security\Resource;

class NavbarMenuItem
{
	protected string $label = 'Test';
	protected string $faIcon = 'chart-simple';

	protected ?NavbarSubmenu $submenu = null;
	protected ?string $link = null;
	protected array $linkArgs = [];
	protected ?Resource $resource = null;

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

	public function isEnabledSubmenu(SecurityUser $user): bool
	{
		$enabled = false;

		if ($this->getAclResource() || count($this->getSubmenu()->getSubMenuItems()) === 0) {
			return $user->isAllowed($this->getAclResource());
		}

		foreach ($this->getSubmenu()->getSubMenuItems() as $subMenuItem) {
			if ($subMenuItem instanceof NavbarSubmenuHeading) {
				continue;
			}
			if (!$subMenuItem->getAclResource() || $user->isAllowed($subMenuItem->getAclResource())) {
				$enabled = true;
				break;
			}
		}

		return $enabled;
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

	public function getLinkArgs(): array
	{
		return $this->linkArgs;
	}

	public function setLinkArgs(array $linkArgs): self
	{
		$this->linkArgs = $linkArgs;
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
				if ($submenuItem instanceof NavbarSubmenuHeading) {
					continue;
				}
				if ($submenuItem->isCurrent($presenter)) {
					return true;
				}
			}
		}

		return $presenter->isLinkCurrent($this->getLink(), $this->getLinkArgs());
	}

	public function getAclResource(): ?Resource
	{
		return $this->resource;
	}

	public function setAclResource(?Resource $resource): self
	{
		$this->resource = $resource;
		return $this;
	}
}