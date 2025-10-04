<?php

namespace ADT\FancyAdmin\Model\Menu\Traits;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuItem;

trait TRolesMenuItem {
	public function addRoleItem(NavbarMenu $menu): void {
		$menu->addMenuItem(
			(new NavbarMenuItem())
				->setLabel('Roles')
				->setLink('Roles:default')
		);
	}
}