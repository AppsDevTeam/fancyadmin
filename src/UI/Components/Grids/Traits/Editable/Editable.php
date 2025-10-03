<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\Editable;

use ADT\Datagrid\Component\DeleteParams;
use ADT\Datagrid\Component\EditParams;

trait Editable
{
	protected function allowEdit(): ?EditParams
	{
		return new EditParams($this->aclResource, 'edit!');
	}

	protected function allowDelete(): ?DeleteParams
	{
		return new DeleteParams($this->aclResource);
	}
}
