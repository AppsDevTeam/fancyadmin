<?php

namespace ADT\FancyAdmin\Model;

class Administration
{
	public function __construct(
		protected string $title,
		protected string $homepagePresenter,
		protected string $signPresenter,
		protected bool $lostPasswordEnabled
	) {}

	public function getHomepagePresenter(): string
	{
		return $this->homepagePresenter;
	}

	public function getSignPresenter(): string
	{
		return $this->signPresenter;
	}

	public function isLostPasswordEnabled(): bool
	{
		return $this->lostPasswordEnabled;
	}
}