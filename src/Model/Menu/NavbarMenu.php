<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\LinkGenerator;

class NavbarMenu
{
	/** @var NavbarMenuItem[] */
	protected array $menuItems = [];

	/** @var array<NavbarMenuItem|NavbarHeading> */
	protected array $items = [];

	protected LinkGenerator $linkGenerator;

	public function addMenuItem(NavbarMenuItem $menuItem): self
	{
		$this->menuItems[] = $menuItem;
		$this->items[] = $menuItem;
		return $this;
	}

	public function addHeading(string $label, bool $toggleable = false, ?string $faIcon = null): self
	{
		$this->items[] = new NavbarHeading($label, $toggleable, $faIcon);
		return $this;
	}

	/**
	 * @return NavbarMenuItem[]
	 */
	public function getMenuItems(): array
	{
		return $this->menuItems;
	}

	/**
	 * @return array<NavbarMenuItem|NavbarHeading>
	 */
	public function getItems(): array
	{
		return $this->items;
	}

	/**
	 * Rozdělí menu na skupiny podle NavbarHeading - potřebné pro nový DOM
	 * (skupina wrapuje své položky, aby šlo v zabaleném režimu ukázat popover na hover).
	 * @return array<array{heading: ?NavbarHeading, items: array<NavbarMenuItem>}>
	 */
	public function getGroups(): array
	{
		$groups = [];
		$current = ['heading' => null, 'items' => []];
		foreach ($this->items as $item) {
			if ($item instanceof NavbarHeading) {
				if ($current['heading'] !== null || $current['items']) {
					$groups[] = $current;
				}
				$current = ['heading' => $item, 'items' => []];
			} else {
				$current['items'][] = $item;
			}
		}
		if ($current['heading'] !== null || $current['items']) {
			$groups[] = $current;
		}
		return $groups;
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
					if ($subItem instanceof NavbarSubmenuHeading) {
						continue;
					}
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