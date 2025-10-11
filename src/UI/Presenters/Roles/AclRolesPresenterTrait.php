<?php

namespace ADT\FancyAdmin\UI\Presenters\Roles;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\AclRoleFormFactoryInject;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\Forms\AclRole\AclRoleFormFactory;
use ADT\FancyAdmin\UI\Components\Grids\AclRole\AclRoleGrid;
use ADT\FancyAdmin\UI\Components\Grids\AclRole\AclRoleGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use ADT\FancyAdmin\Model\Entities\AclRole;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\UI\Presenters\SecurityCheckAttribute;

trait AclRolesPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use AclRoleFormFactoryInject;
	use AclRoleQueryFactoryInject;

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ROLES_AND_PERMISSIONS)]
	public function actionDefault(): void
	{
	}

	public function renderDefault(): void
	{
		$this->template->setFile(__DIR__ . '/default.latte');
	}

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ROLES_AND_PERMISSIONS)]
	public function handleNew(): void
	{
		$this->redrawSidePanel();
	}

	#[SecurityCheckAttribute(AclResourceNameEnum::BACKOFFICE_ROLES_AND_PERMISSIONS)]
	public function handleEdit(AclRole $role): void
	{
		$this->entity = $role;
		$this->redrawSidePanel();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_roleFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_aclRoleQueryFactory->create();
	}

	protected function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Medium;
	}

	public function createComponentRoleGrid(AclRoleGridFactory $factory): AclRoleGrid
	{
		return $factory->create();
	}
}
