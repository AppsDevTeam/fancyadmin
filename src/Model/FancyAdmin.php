<?php

namespace ADT\FancyAdmin\Model;

use Nette\Security\Resource;

class FancyAdmin
{
	public function __construct(
		protected string $project,
		protected string $projectName,
		protected string $adminHostPath,
		protected bool $lostPasswordEnabled,
		protected string $logoPublicPath,
		protected string $logoBitmapPublicPath,
		protected string $defaultCustomerRoute,
		protected string $defaultBackofficeRoute,
		protected bool $hmr,
		protected Resource $customerAclResource,
		protected Resource $backofficeAclResource,
		protected Resource $fullDataAclResource,
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

	public function getDefaultCustomerRoute(): string
	{
		return $this->defaultCustomerRoute;
	}

	public function getDefaultBackofficeRoute(): string
	{
		return $this->defaultBackofficeRoute;
	}

	public function getHmr(): bool
	{
		return $this->hmr;
	}

	public function getCustomerAclResource(): Resource
	{
		return $this->customerAclResource;
	}

	public function getBackofficeAclResource(): Resource
	{
		return $this->backofficeAclResource;
	}
	
	public function getFullDataAclResource(): Resource
	{
		return $this->fullDataAclResource;
	}
}