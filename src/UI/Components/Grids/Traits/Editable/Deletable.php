<?php

namespace ADT\FancyAdmin\UI\Components\Grids\Traits\Editable;

use ADT\Datagrid\Component\DeleteParams;

trait Deletable
{
	protected function allowDelete(): ?DeleteParams
	{
		return new DeleteParams($this->aclResource);
	}
}
