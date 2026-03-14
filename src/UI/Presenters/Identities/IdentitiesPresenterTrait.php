<?php

namespace ADT\FancyAdmin\UI\Presenters\Identities;

use ADT\DoctrineForms\BaseFormInterface;
use ADT\DoctrineComponents\Entities\Entity;
use ADT\FancyAdmin\DI\Injects\IdentityFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\IdentityQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Identity;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Controls\SidePanel\SidePanelSize;
use ADT\FancyAdmin\UI\Components\Grids\Identity\IdentityGrid;
use ADT\FancyAdmin\UI\Components\Grids\Identity\IdentityGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;

trait IdentitiesPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use IdentityFormFactoryInject;
	use IdentityQueryFactoryInject;

	public function actionDefault(): void
	{
		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function handleEdit(Identity $user): void
	{
		$this->entity = $user;
		$this->redrawSidePanel();
	}

	public function handleNew(): void
	{
		$this->redrawSidePanel();
	}

	public function createComponentUserGrid(IdentityGridFactory $factory): IdentityGrid
	{
		return $factory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_identityFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_identityQueryFactory->create();
	}

	protected function getSidePanelSize(): SidePanelSize
	{
		return SidePanelSize::Extreme;
	}
}