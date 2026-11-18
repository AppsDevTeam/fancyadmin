<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Identity;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Enums\IdentityScopeEnum;
use ADT\FancyAdmin\Model\Queries\IdentityQuery;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\AnonymizeIdentity\AnonymizeIdentity;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\IdentityData;
use ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword\ResetPassword;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SignInAsIdentity\SignInAsIdentity;

trait IdentityGridTrait
{
	use ResetPassword;
	use Editable;
	use SignInAsIdentity;
	use IdentityData;
	use AnonymizeIdentity;

	/** null = seznam se nedeli a zobrazuje vsechny uzivatele. */
	public ?IdentityScopeEnum $scope = null;

	public function initGrid(DataGrid $grid): void
	{
		$this->addIdentityData($grid);

		$grid->addColumnText('roles', 'fcadmin.grids.user.labels.roles', 'roleNames');

		// Single-tenant: účty nejsou
		if (!$this->_securityUser->isMultiTenant()) {
			return;
		}

		$grid->addColumnText('accounts', 'fcadmin.grids.user.labels.accounts', 'accountNames');
	}

	protected function initQueryObject($queryObject): void
	{
		if ($this->scope && $queryObject instanceof IdentityQuery) {
			$queryObject->byScope($this->scope);
		}
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return IdentityQueryFactory::class;
	}
}
