<?php

namespace ADT\FancyAdmin\UI\Presenters\ApiKeys;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\DoctrineForms\BaseFormInterface;
use ADT\FancyAdmin\DI\Injects\ApiKeyFormFactoryInject;
use ADT\FancyAdmin\DI\Injects\ApiKeyQueryFactoryInject;
use ADT\FancyAdmin\Model\Entities\ApiKey;
use ADT\FancyAdmin\Model\Queries\Abstract\BaseQuery;
use ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGrid;
use ADT\FancyAdmin\UI\Components\Grids\ApiKey\ApiKeyGridFactory;
use ADT\FancyAdmin\UI\Presenters\PresenterTrait;
use ADT\FancyAdmin\UI\Presenters\SidePanel;

trait ApiKeysPresenterTrait
{
	use SidePanel;
	use PresenterTrait;
	use ApiKeyFormFactoryInject;
	use ApiKeyQueryFactoryInject;

	public function actionDefault(?ApiKey $apiKey = null): void
	{
		if ($apiKey) {
			$this->entity = $apiKey;
		}

		$this->template->setFile(__DIR__ . '/default.latte');
	}

	public function handleEdit(ApiKey $apiKey): void
	{
		$this->entity = $apiKey;
		$this->redrawSidePanel();
	}

	public function handleNew(): void
	{
		$this->entity = null;
		$this->redrawSidePanel();
	}

	public function createComponentApiKeyGrid(ApiKeyGridFactory $factory): ApiKeyGrid
	{
		return $factory->create();
	}

	protected function getForm(): BaseFormInterface
	{
		return $this->_apiKeyFormFactory->create();
	}

	protected function getQueryObject(): BaseQuery
	{
		return $this->_apiKeyQueryFactory->create();
	}

	protected function getEntity(): Entity|callable|null
	{
		return $this->entity;
	}
}
