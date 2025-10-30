<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Grids\Configuration;

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\DeleteParams;
use ADT\Datagrid\Component\EditParams;
use ADT\FancyAdmin\Model\Entities\Enums\AclResourceNameEnum;
use ADT\FancyAdmin\Model\Queries\Factories\ConfigurationQueryFactory;
use ADT\FancyAdmin\UI\Components\Grids\Traits\Editable\Editable;

trait ConfigurationGridTrait
{
	use Editable;

	protected AclResourceNameEnum $aclResource = AclResourceNameEnum::BACKOFFICE_CONFIGURATIONS;

	public function initGrid(DataGrid $grid): void
	{
		$grid->addColumnText('key', 'Key');
		$grid->addColumnText('type', 'Type');
		$grid->addColumnText('value', 'Value');
		$grid->addColumnText('options', 'Options');
	}

	protected function allowEdit(): ?EditParams
	{
		return new EditParams($this->aclResource, 'edit!');
	}

	protected function allowDelete(): ?DeleteParams
	{
		return null;
	}

	protected function getQueryObjectFactoryClass(): string
	{
		return ConfigurationQueryFactory::class;
	}
}
