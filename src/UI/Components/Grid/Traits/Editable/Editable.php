<?php

namespace namespace ADT\Datagrid\Component\Grid\Traits\Editable;

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
