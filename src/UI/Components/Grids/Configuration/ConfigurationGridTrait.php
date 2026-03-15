<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Configuration;

use ADT\Datagrid\Component\DataGrid;
use ADT\FancyAdmin\Model\Entities\Configuration;
use ADT\FancyAdmin\Model\Entities\Enums\ConfigurationTypeEnum;
use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;
use Nette\Utils\Json;

trait ConfigurationGridTrait
{
	use Editable;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('name', 'Name');
		$grid->addColumnText('value', 'Value')
			->setRenderer(function(Configuration $configuration) {
				if ($configuration->getType() === ConfigurationTypeEnum::TYPE_JSON) {
					return Json::encode($configuration->getValue(), pretty: true);
				} elseif ($configuration->getType() === ConfigurationTypeEnum::TYPE_FILE) {
					return $configuration->getFile()->getUrl();
				}
				return $configuration->getValue();
			});
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ConfigurationQueryFactory::class;
	}
}
