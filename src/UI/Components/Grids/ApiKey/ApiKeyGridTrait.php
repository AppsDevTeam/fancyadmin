<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\ApiKey;

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\DeleteParams;
use ADT\FancyAdmin\Model\Queries\Factories\ApiKeyQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;

trait ApiKeyGridTrait
{
	use Editable;
	use SearchFilter;

	public function initGrid(DataGrid $grid): void
	{
		$this->addSearchFilter($grid, ['name']);

		$grid->addColumnText('name', 'fcadmin.presenters.apiKeys.grid.name');

		$grid->addColumnText('key', 'fcadmin.presenters.apiKeys.grid.fingerprint', 'keyFingerprint');

		if ($this->_securityUser->isMultiTenant() && $this->_securityUser->isAllowedFullDataAclResource()) {
			$grid->addColumnText('account', 'fcadmin.presenters.apiKeys.grid.account', 'account.name');
		}
	}

	protected function allowDelete(): ?DeleteParams
	{
		return new DeleteParams();
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ApiKeyQueryFactory::class;
	}
}
