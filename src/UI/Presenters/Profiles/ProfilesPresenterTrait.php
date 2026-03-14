<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Presenters\Profiles;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\AclRoleQueryFactoryInject;
use ADT\FancyAdmin\DI\Injects\EntityManagerInject;
use ADT\FancyAdmin\Model\Entities\Profile;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\Model\Queries\Factories\ProfileQueryFactory;
use ADT\FancyAdmin\UI\Components\Forms\Profile\ProfileFormFactory;
use ADT\FancyAdmin\UI\Components\Grids\Profile\ProfileGrid;
use ADT\FancyAdmin\UI\Components\Grids\Profile\ProfileGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;
use Kdyby\Autowired\Attributes\Autowire;

trait ProfilesPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use EntityManagerInject;
	use AclRoleQueryFactoryInject;

	#[Autowire]
	protected ProfileQueryFactory $_profileQueryFactory;

	#[Autowire]
	protected ProfileFormFactory $_profileFormFactory;

	public function actionDefault(?Profile $profile = null): void
	{
		if ($profile) {
			$this->entity = $profile;
		}

		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function actionDetail(Profile $profile): void
	{
		$this->entity = $profile;
	}

	public function handleNew(): void
	{
		$this->redrawSidePanel();
	}

	public function handleEdit(Profile $spareParts): void
	{
		$this->entity = $spareParts;
		$this->redrawSidePanel();
	}

	public function createComponentProfileGrid(ProfileGridFactory $factory): ProfileGrid
	{
		return $factory->create();
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_profileFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_profileQueryFactory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}
}
