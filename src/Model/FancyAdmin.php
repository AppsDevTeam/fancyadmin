<?php

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Menu\NavbarMenu;
use ADT\FancyAdmin\Model\Menu\NavbarMenuFactory;
use ADT\FancyAdmin\Model\Queries\Factories\AccountQueryFactory;
use Nette\Application\LinkGenerator;

class FancyAdmin
{
	public function __construct(
		protected string $project,
		protected string $projectName,
		protected string $adminHostPath,
		protected string $homepagePresenter,
		protected bool $lostPasswordEnabled,
		protected string $logoFileName,
		protected NavbarMenuFactory $navbarMenuFactory,
		protected LinkGenerator $linkGenerator,
		protected AccountQueryFactory $accountQueryFactory,
	) {}

	public function getProject(): string
	{
		return $this->project;
	}
	
	public function getProjectName(): string
	{
		return $this->projectName;
	}

	public function getLogoFileName(): string
	{
		return $this->logoFileName;
	}

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