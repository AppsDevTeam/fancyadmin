<?php

namespace ADT\FancyAdmin\Model;

class Administration
{
	public function __construct(
		protected string $adminHostPath,
		protected string $homepagePresenter,
		protected bool $lostPasswordEnabled
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
}