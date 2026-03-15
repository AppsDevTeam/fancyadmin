<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\LinkGenerator;

class NavbarMenu
{
	/** @var NavbarMenuItem[] */
	protected array $menuItems = [];

	protected LinkGenerator $linkGenerator;

	public function addMenuItem(NavbarMenuItem $menuItem): self
	{
		$this->menuItems[] = $menuItem;
		return $this;
	}

	/**
	 * @return NavbarMenuItem[]
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

	/**
	 * Auto-sets ACL resources on menu items that don't have one explicitly set,
	 * based on the link's presenter name and the current module.
	 *
	 * E.g. module "PortalBackoffice" + link "Devices:default" → resource "portalBackoffice.devices"
	 */
	public function resolveAclResources(string $module): self
	{
		foreach ($this->menuItems as $menuItem) {
			if (!$menuItem->getAclResource() && $menuItem->getLink()) {
				$presenterName = explode(':', $menuItem->getLink())[0];
				$menuItem->setAclResource(new StringResource(lcfirst($module) . '.' . lcfirst($presenterName)));
			}

			if ($menuItem->getSubmenu()) {
				foreach ($menuItem->getSubmenu()->getSubMenuItems() as $subItem) {
					if (!$subItem->getAclResource()) {
						$presenterName = explode(':', $subItem->getLink())[0];
						$subItem->setAclResource(new StringResource(lcfirst($module) . '.' . lcfirst($presenterName)));
					}
				}
			}
		}

		return $this;
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
}