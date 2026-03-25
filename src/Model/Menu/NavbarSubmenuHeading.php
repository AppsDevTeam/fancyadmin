<?php

namespace ADT\FancyAdmin\Model\Menu;

class NavbarSubmenuHeading
{
	public function __construct(
		protected string $label
	) {}

	public function getLabel(): string
	{
		return $this->label;
	}
}
