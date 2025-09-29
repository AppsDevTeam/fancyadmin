<?php

namespace ADT\FancyAdmin\Model;

use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;

class FancyAdmin
{
	public function __construct(
		protected string $project,
		protected string $projectName,
		protected string $adminHostPath,
		protected bool $lostPasswordEnabled,
		protected string $logoPublicPath,
		protected string $logoBitmapPublicPath,
		protected bool $hmr,
		protected AclResourceNameEnum $loginContext
	) {}

	public function getProject(): string
	{
		return $this->project;
	}
	
	public function getProjectName(): string
	{
		return $this->projectName;
	}

	public function getLogoPublicPath(): string
	{
		return $this->logoPublicPath;
	}

	public function getLogoBitmapPublicPath(): string
	{
		return $this->logoBitmapPublicPath;
	}

	public function getAdminHostPath(): string
	{
		return $this->adminHostPath;
	}

	public function isLostPasswordEnabled(): bool
	{
		return $this->lostPasswordEnabled;
	}
	
	public function getHmr(): bool
	{
		return $this->hmr;
	}
	
	public function getLoginContext(): AclResourceNameEnum
	{
		return $this->loginContext;
	}
}