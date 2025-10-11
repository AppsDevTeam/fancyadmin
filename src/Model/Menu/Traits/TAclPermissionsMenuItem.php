<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;

trait TAclPermissionsMenuItem {
	public function addPermissionsItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Permissions')
				->setLink('Permissions:default')
		);
	}
}