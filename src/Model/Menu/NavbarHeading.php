<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\Model\Menu;

class NavbarHeading
{
	public function __construct(
		protected string $label,
		protected bool $toggleable = false,
		protected ?string $faIcon = null,
	) {
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function isToggleable(): bool
	{
		return $this->toggleable;
	}

	public function getFaIcon(): ?string
	{
		return $this->faIcon;
	}
}
