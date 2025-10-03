<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\Editable;

use ADT\Datagrid\Component\EditParams;

trait Editable
{
	protected function allowEdit(): ?EditParams
	{
		return new EditParams($this->aclResource, 'edit!');
	}
}
