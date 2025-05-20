<?php

namespace ADT\FancyAdmin\Model\Menu;

use Nette\Application\UI\Component;

class NavbarSubmenuItem
{
	protected string $label = 'Test';
	protected string $faIcon = 'chart-simple';
	protected string $link = '#';

	public function getLabel(): string
	{
		return $this->label;
	}

	public function setLabel(string $label): self
	{
		$this->label = $label;
		return $this;
	}

	public function getLink(): string
	{
		return $this->link;
	}

	public function setLink(string $link): self
	{
		$this->link = $link;
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

	public function isCurrent(Component $presenter): bool
	{
		return $presenter->isLinkCurrent($this->getLink());
	}


}