<?php

namespace ADT\FancyAdmin\Model\Menu;

interface UserMenuFactory
{
	public function create(): UserMenu;
}