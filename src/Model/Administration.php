<?php

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use Nette\Application\LinkGenerator;

class Administration
{
	public function __construct(
		protected string $adminHostPath,
		protected string $homepagePresenter,
		protected bool $lostPasswordEnabled,
		protected NavbarMenuFactory $navbarMenuFactory,
		protected LinkGenerator $linkGenerator,
	) {}

	public function getAdminHostPath(): string
	{
		return $this->adminHostPath;
	}

	public function getHomepagePresenter(): string
	{
		return $this->homepagePresenter;
	}

	public function isLostPasswordEnabled(): bool
	{
		return $this->lostPasswordEnabled;
	}

	public function getNavbarMenu(): NavbarMenu
	{
		return $this->navbarMenuFactory->create()->setLinkGenerator($this->linkGenerator);
	}
}