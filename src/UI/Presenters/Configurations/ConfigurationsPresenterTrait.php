<?php

namespace ADT\FancyAdmin\UI\Presenters\Configurations;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\ConfigurationFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\ConfigurationQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Grids\Configuration\ConfigurationGrid;
use ADT\FancyAdmin\UI\Components\Grids\Configuration\ConfigurationGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;

trait ConfigurationsPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use ConfigurationFormFactoryInject;
	use ConfigurationQueryFactoryInject;

	public function actionDefault(?Configuration $configuration = null): void
	{
		if ($configuration) {
			$this->entity = $configuration;
		}

		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function actionDetail(Configuration $configuration): void
	{
		$this->entity = $configuration;
	}

	public function handleEdit(Configuration $spareParts): void
	{
		$this->entity = $spareParts;
		$this->redrawSidePanel();
	}

	public function createComponentConfigurationGrid(ConfigurationGridFactory $factory): ConfigurationGrid
	{
		return $factory->create();
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_configurationFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_configurationQueryFactory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}
}
