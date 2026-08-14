<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\ApiKey;

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\DeleteParams;
use ADT\FancyAdmin\Model\Entities\ApiKey;
use ADT\FancyAdmin\Model\Queries\Factories\ApiKeyQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SearchFilter;

trait ApiKeyGridTrait
{
	use Editable;
	use SearchFilter;

	/** Kolik znaků otisku klíče se zobrazuje v gridu. */
	protected int $keyFingerprintLength = 8;

	public function initGrid(DataGrid $grid): void
	{
		$this->addSearchFilter($grid, ['name']);

		$grid->addColumnText('name', 'fcadmin.presenters.apiKeys.grid.name');

		// samotný klíč zobrazit nelze (uložený je jen otisk), pro rozlišení stačí jeho začátek
		$grid->addColumnText('key', 'fcadmin.presenters.apiKeys.grid.fingerprint')
			->setRenderer(fn(ApiKey $apiKey) => $apiKey->getKey()
				? mb_substr($apiKey->getKey(), 0, $this->keyFingerprintLength) . '…'
				: '');

		if ($this->_securityUser->isAllowedFullDataAclResource()) {
			$grid->addColumnText('account', 'fcadmin.presenters.apiKeys.grid.account')
				->setRenderer(fn(ApiKey $apiKey) => $apiKey->getAccount()?->getName() ?? '');
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
