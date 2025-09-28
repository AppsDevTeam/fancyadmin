<?php

namespace ADT\FancyAdmin\Model;

class FancyAdmin
{
	public function __construct(
		protected string $project,
		protected string $projectName,
		protected string $adminHostPath,
		protected bool $lostPasswordEnabled,
		protected string $logoFileName,
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

	public function isLostPasswordEnabled(): bool
	{
		return $this->lostPasswordEnabled;
	}
}