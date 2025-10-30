<?php

namespace ADT\FancyAdmin\UI\Presenters\Accounts;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\AccountFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\AccountQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Account;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\UI\Components\Grids\Account\AccountGrid;
use ADT\FancyAdmin\UI\Components\Grids\Account\AccountGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;
use ADT\FancyAdmin\UI\Presenters\SidePanel;

trait AccountsPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use AccountFormFactoryInject;
	use AccountQueryFactoryInject;

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ACCOUNTS)]
	public function actionDefault(): void
	{
		$this->template->setFile(__DIR__ . '/default.latte');
	}

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ACCOUNTS)]
	public function handleEdit(Account $account): void
	{
		$this->entity = $account;
		$this->redrawSidePanel();
	}

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ACCOUNTS)]
	public function handleNew(): void
	{
		$this->redrawSidePanel();
	}

	public function createComponentAccountGrid(AccountGridFactory $factory): AccountGrid
	{
		return $factory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_accountFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_accountQueryFactory->create();
	}
}
