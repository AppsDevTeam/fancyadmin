<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Identity;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\MailerInject;
use ADT\FancyAdmin\DI\Injects\SecurityUserInject;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Factories\IdentityQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\AnonymizeIdentity\AnonymizeIdentity;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use ADT\FancyAdmin\UI\Components\Grids\Traits\IdentityData;
use ADT\FancyAdmin\UI\Components\Grids\Traits\ResetPassword\ResetPassword;
use ADT\FancyAdmin\UI\Components\Grids\Traits\SignInAsIdentity\SignInAsIdentity;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;
use Exception;
use ReflectionException;

trait IdentityGridTrait
{
	use ResetPassword;
	use Editable;
	use SignInAsIdentity;
	use IdentityData;
	use AnonymizeIdentity;

	public function initGrid(DataGrid $grid, array $searchFields = []): void
	{
		$this->addIdentityData($grid, searchFields: $searchFields);

		$grid->addColumnText('roles', 'fcadmin.grids.user.labels.roles')
			->setRenderer(function (Identity $identity) {
				$roles = [];
				foreach ($identity->getProfiles() as $profile) {
					foreach ($profile->getRoles() as $role) {
						$roles[$role->getId()] = $role->getName();
					}
				}
				foreach ($identity->getRoles() as $role) {
					$roles[$role->getId()] = $role->getName();
				}
				return implode(', ', $roles);
			});

		$grid->addColumnText('accounts', 'fcadmin.grids.user.labels.accounts')
			->setRenderer(function (Identity $identity) {
				return implode(', ', array_map(fn($account) => $account->getName(), $identity->getAccounts()));
			});
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return IdentityQueryFactory::class;
	}
}
