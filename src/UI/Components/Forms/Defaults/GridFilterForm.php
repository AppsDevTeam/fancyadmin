<?php

declare(strict_types=1);

namespace ADT\FancyAdmin\UI\Components\Forms\Defaults;

use ADT\Datagrid\Component\GridFilter\GridFilterFormTrait;
use ADT\Datagrid\Model\Queries\GridFilterQuery;
use ADT\FancyAdmin\DI\Injects\GridFilterQueryFactoryInject;

class GridFilterForm extends DefaultBaseForm implements \ADT\Datagrid\Component\GridFilter\GridFilterForm
{
	use GridFilterFormTrait;
	use GridFilterQueryFactoryInject;

	protected function getGridFilterQuery(): GridFilterQuery
	{
		return $this->_gridFilterQueryFactory->create();
	}
}
