<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Defaults\Backoffice;

use ADT\FancyAdmin\Model\Menu\Traits\TMenuItemsTrait;
use ADT\FancyAdmin\Model\Menu\UserMenu;

class UserMenuFactory implements \ADT\FancyAdmin\Model\Menu\UserMenuFactory
{
	use TMenuItemsTrait;

	public function create(): UserMenu
	{
		return new UserMenu();
	}
}
